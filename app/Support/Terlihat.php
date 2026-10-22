<?php

namespace App\Support;

use App\Enums\PeranPengguna;
use App\Models\Laporan;
use App\Models\Rekomendasi;
use App\Models\Satker;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Filter hak akses di satu tempat; yang keluar sudah dipangkas, jadi semua hitungan di bawahnya
 * mewarisinya. Satu-satunya pemeriksa hak akses satuan kerja: `sasarans.satker_id`.
 * Satuan kerja melihat rekomendasi yang punya baris untuknya, dan temuan di tempatnya
 * (`hanya_terperiksa`). Bagian satuan kerja lain tidak pernah ikut ke tampilan; namanya di kalimat
 * disamarkan.
 */
class Terlihat
{
    public function __construct(private User $pengguna) {}

    public static function untuk(?User $pengguna = null): self
    {
        return new self($pengguna ?? auth()->user());
    }

    /** Satker dan Pimpinan hanya membuka unit yang ditetapkan Admin DTI. */
    public function seluruhnya(): bool
    {
        return ! $this->pengguna->peranAktif()->terbatasSatker();
    }

    /** Satker dalam cakupan (satu untuk akun satuan kerja; satu atau lebih untuk pimpinan). @return list<int> */
    private function satkerIds(): array
    {
        return array_map('intval', (array) $this->pengguna->satkerAktifId());
    }

    /** Daftar pilihan unit juga mengikuti hak akses; tanpa cakupan berarti kosong. */
    public function satkers(): Builder
    {
        return Satker::query()->when(! $this->seluruhnya(),
            fn ($q) => $q->whereKey($this->satkerIds() ?: [0]));
    }

    /* ---- Rekomendasi ---- */

    public function rekomendasi(): Builder
    {
        $q = Rekomendasi::query();
        if (! $this->seluruhnya()) {
            $satker = $this->satkerIds() ?: [0];
            $q->whereHas('sasaran', fn ($s) => $s->whereIn('sasarans.satker_id', $satker));
        }

        return $q;
    }

    public function bolehLihatRekomendasi(Rekomendasi $r): bool
    {
        return $this->seluruhnya() || $r->dituju($this->pengguna->satkerAktifId());
    }

    /**
     * Pangkas rekomendasi yang sudah dimuat dan kecilkan tagihannya jadi bagian pengguna ini. Model
     * yang dipangkas TIDAK BOLEH disimpan.
     */
    public function pangkasRekomendasi(Rekomendasi $r): Rekomendasi
    {
        if ($this->seluruhnya()) {
            return $r;
        }

        $ids = $this->satkerIds();
        abort_unless($ids, 403, 'Cakupan Satker belum ditentukan. Hubungi Admin DTI.');
        $satker = $this->pengguna->satkerAktifModel();
        $semua = $r->daftarSasaran();

        $baris = $semua->whereIn('satker_id', $ids)->values();
        // Dashboard dan rangkuman Pimpinan tidak mencampur angka unit lain.
        $r->barisSemua = $this->pengguna->peranAktif() === PeranPengguna::PIMPINAN ? $baris : $semua;
        $r->setRelation('sasaran', $baris);
        $r->nilai_pulih = (int) $baris->sum('nilai');

        /* Baris yang ikut termuat lewat tindakannya dipangkas juga. */
        if ($r->relationLoaded('tindakan')) {
            $r->setRelation('tindakan', $r->tindakan->whereIn('id', $baris->pluck('tindakan_id'))->values());
            $r->tindakan->each(function ($tk) use ($ids) {
                if ($tk->relationLoaded('sasaran')) {
                    $tk->setRelation('sasaran', $tk->sasaran->whereIn('satker_id', $ids)->values());
                }
            });
        }

        $id = $baris->pluck('id')->all();
        $milikku = fn ($x) => $x->sasaran_id === null || in_array($x->sasaran_id, $id, true);

        foreach (['tanggapan', 'pemulihan', 'pengembalian', 'tolakanBpk'] as $rel) {
            if ($r->relationLoaded($rel)) {
                $r->setRelation($rel, $r->getRelation($rel)->filter($milikku)->values());
            }
        }

        if ($r->relationLoaded('tanggapan')) {
            $r->tanggapan->each(fn ($x) => $x->uraian = self::teksUntuk($x->uraian, $satker));
        }

        if ($r->relationLoaded('permintaanDokumen')) {
            $r->setRelation('permintaanDokumen', $r->permintaanDokumen->filter($milikku)->values()
                ->each(fn ($x) => $x->alasan = self::teksUntuk($x->alasan, $satker)));
        }

        if ($r->relationLoaded('telaah')) {
            $r->setRelation('telaah', $r->telaah->filter($milikku)->values()
                ->each(fn ($x) => $x->catatan = self::teksUntuk(self::catatanUntuk($x->catatan, $satker), $satker)));
        }

        if ($r->relationLoaded('keputusan')) {
            $r->setRelation('keputusan', $r->keputusan->filter($milikku)->values()->each(function ($x) use ($satker, $ids) {
                $x->catatan = self::teksUntuk(self::catatanUntuk($x->catatan, $satker), $satker);
                $x->catatan_satker = collect($x->catatan_satker ?? [])
                    ->filter(fn ($c) => in_array((int) ($c['satker_id'] ?? 0), $ids, true))->values()->all();
            }));
        }

        if ($r->relationLoaded('surat')) {
            $r->setRelation('surat', $r->surat->filter($milikku)->values()->each(function ($x) use ($satker) {
                $x->perihal = self::teksUntuk($x->perihal, $satker);
                $x->catatan = self::teksUntuk($x->catatan, $satker);
            }));
        }

        /* Riwayat milik rekomendasi difilter menurut pelakunya, kalimatnya disamarkan. */
        if ($r->relationLoaded('riwayat')) {
            $r->setRelation('riwayat', $r->riwayat
                ->reject(fn ($x) => self::punyaSatkerLain($x->label_aktor, $satker))
                ->values()
                ->each(fn ($x) => $x->aksi = self::teksUntuk($x->aksi, $satker)));
        }

        /* Surat pemeriksaan asli tidak pernah diberikan ke satuan kerja. */
        if ($r->relationLoaded('lampiran')) {
            $r->setRelation('lampiran', $r->lampiran
                ->reject(fn ($x) => $x->surat_asli || self::punyaSatkerLain($x->label_oleh, $satker))
                ->filter($milikku)
                ->values());
        }

        return $r;
    }

    /* ---- Laporan ---- */

    /**
     * Laporan yang menyangkut pengguna ini; bagi satuan kerja: punya baris untuknya atau temuan di
     * tempatnya.
     */
    public function laporan(): Builder
    {
        $q = Laporan::query();
        if ($this->seluruhnya()) {
            return $q;
        }

        $satker = $this->satkerIds() ?: [0];

        return $q->where(function ($x) use ($satker) {
            $x->whereHas('temuan.satkers', fn ($s) => $s->whereIn('satkers.id', $satker))
                ->orWhereHas('temuan.rekomendasi.sasaran',
                    fn ($s) => $s->whereIn('sasarans.satker_id', $satker));
        });
    }

    /**
     * Pangkas isi laporan: temuan yang tidak menyangkut dibuang; yang hanya sebagai tempat kejadian
     * tanpa rekomendasi.
     */
    public function pangkas(Laporan $l): Laporan
    {
        if ($this->seluruhnya()) {
            return $l;
        }

        $ids = $this->satkerIds();
        abort_unless($ids, 403, 'Cakupan Satker belum ditentukan. Hubungi Admin DTI.');

        $temuan = $l->temuan->map(function ($t) use ($ids) {
            $punyaku = $t->rekomendasi
                ->filter(fn ($r) => $r->dituju($ids))
                ->map(fn ($r) => $this->pangkasRekomendasi($r))
                ->values();

            if ($punyaku->isEmpty() && ! $t->mengenai($ids)) {
                return null;
            }

            $t->setRelation('rekomendasi', $punyaku);
            /* Bukan tempat kejadiannya: yang disebut Satker dalam cakupan yang punya barisnya. */
            $t->setRelation('satkers', $t->satkers->whereIn('id', $ids)->values()
                ->whenEmpty(fn () => $punyaku->flatMap(fn ($r) => $r->daftarSasaran()->map->satker)
                    ->filter()->unique('id')->values()));
            $t->nilai = (int) $punyaku->sum('nilai_pulih');
            $t->hanya_terperiksa = $punyaku->isEmpty();

            return $t;
        })->filter()->values();

        $l->setRelation('temuan', $temuan);
        $l->setRelation('lampiran', collect());

        return $l;
    }

    public function bolehLihatLaporan(Laporan $l): bool
    {
        if ($this->seluruhnya()) {
            return true;
        }
        $satker = $this->pengguna->satkerAktifId();

        if (! $satker) {
            return false;
        }

        return $l->temuan->contains(fn ($t) => $t->mengenai($satker)
            || $t->rekomendasi->contains(fn ($r) => $r->dituju($satker)));
    }

    /** Daftar laporan yang sudah dipangkas isinya, siap ditampilkan. */
    public function daftarLaporan(array $muat = [], bool $ringkas = false): Collection
    {
        $bawaan = $ringkas ? ['temuan.satkers', 'temuan.rekomendasi.sasaran.satker']
            : ['temuan.satkers', 'temuan.kategori', 'temuan.kategoriIntern',
                'temuan.rekomendasi.sasaran.satker', 'temuan.rekomendasi.tindakan.bentuk',
                'temuan.rekomendasi.pemulihan', 'temuan.rekomendasi.tolakanBpk',
                'temuan.rekomendasi.permintaanDokumen.item', 'temuan.rekomendasi.riwayat',
                'lampiran'];

        /* Urutan dasar = urutan pencatatan. */
        return $this->laporan()
            ->with(array_unique(array_merge($bawaan, $muat)))
            ->orderBy('id')
            ->get()
            /* Induk temuan dan rekomendasi dihubungkan ke model yang sudah dimuat, supaya tidak
               memuat ulang satu per satu. */
            ->each(function ($l) {
                foreach ($l->temuan as $t) {
                    $t->setRelation('laporan', $l);
                    foreach ($t->rekomendasi as $r) {
                        $r->setRelation('temuan', $t);
                    }
                }
            })
            ->map(fn ($l) => $this->pangkas($l))
            ->filter(fn ($l) => $l->temuan->isNotEmpty())
            ->values();
    }

    /* ---- Penyamaran kalimat ---- */

    /** @var list<string>|null */
    private static ?array $nama = null;

    /** Daftar nama disimpan selama proses, dilepas tiap kali unit kerja berubah. */
    public static function lupakan(): void
    {
        self::$nama = null;
    }

    /** Nama panjang dan pendek seluruh satuan kerja, yang terpanjang dulu. */
    private static function semuaNama(): array
    {
        return self::$nama ??= Satker::all()
            ->flatMap(fn ($s) => [$s->nama, $s->nama_pendek])
            ->filter()->unique()
            ->sortByDesc(fn ($x) => mb_strlen($x))
            ->values()->all();
    }

    /** @return Collection<int, Satker> */
    private static function milik(Satker|Collection $saya): Collection
    {
        return $saya instanceof Satker ? collect([$saya]) : $saya;
    }

    /** Nama itu milik satuan kerja lain (bukan "Setba", bukan Satker dalam cakupan). */
    public static function punyaSatkerLain(?string $nama, Satker|Collection $saya): bool
    {
        $nama = trim((string) $nama);

        return $nama !== '' && in_array($nama, self::semuaNama(), true)
            && ! self::milik($saya)->contains(fn ($s) => $nama === $s->nama || $nama === $s->nama_pendek);
    }

    /**
     * Deret nama satuan kerja di kalimat diganti: nama sendiri bila termasuk, selain itu "satuan
     * kerja yang dituju".
     */
    public static function teksUntuk(?string $teks, Satker|Collection $saya): ?string
    {
        if (! $teks) {
            return $teks;
        }
        $pola = implode('|', array_map(fn ($x) => preg_quote($x, '/'), self::semuaNama()));
        $deret = "/(?:{$pola})(?:\\s*(?:,|dan)\\s*(?:{$pola}))*/u";
        $milik = self::milik($saya);

        return preg_replace_callback($deret, function ($m) use ($milik) {
            $ada = $milik->filter(fn ($s) => str_contains($m[0], $s->nama) || str_contains($m[0], $s->namaPendek()));

            return $ada->isNotEmpty() ? $ada->map->namaPendek()->join(', ') : 'satuan kerja yang dituju';
        }, $teks);
    }

    /** Buang penggalan " · " yang berawalan nama satuan kerja lain. */
    public static function catatanUntuk(?string $teks, Satker|Collection $saya): string
    {
        return collect(explode(' · ', (string) $teks))
            ->reject(function ($bagian) use ($saya) {
                $b = mb_strpos($bagian, ': ');

                return $b !== false && self::punyaSatkerLain(mb_substr($bagian, 0, $b), $saya);
            })
            ->join(' · ');
    }
}
