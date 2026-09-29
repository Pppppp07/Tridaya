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
 * Filter hak akses, ditaruh di satu tempat — padanan `laporanTerlihat`
 * prototipe.
 *
 * Yang keluar dari kelas ini sudah dipangkas, jadi setiap perhitungan di
 * bawahnya — jumlah rekomendasi, nilai temuan, progres — mewarisi filternya
 * tanpa perlu diingat satu per satu.
 *
 * SATU-SATUNYA pemeriksa hak akses satuan kerja adalah `sasarans.satker_id`.
 *
 * Yang boleh dilihat satuan kerja pada sebuah laporan ada dua macam:
 *
 *   1. rekomendasi yang punya baris untuknya — ini pekerjaannya;
 *   2. temuan yang terjadi di tempatnya walau rekomendasinya jatuh ke pihak
 *      lain — ini haknya untuk tahu, tapi bukan pekerjaannya
 *      (`hanya_terperiksa`).
 *
 * Di dalam rekomendasi yang dipikul bersama, bagian satuan kerja lain tidak
 * pernah ikut ke tampilan: barisnya, setorannya, berkasnya, surat dan
 * catatannya. Nama satuan kerja lain di dalam kalimat disamarkan. Keadaan
 * rekomendasi seutuhnya tetap dihitung dari seluruh barisnya
 * (`Rekomendasi::$barisSemua`), sama seperti prototipe.
 */
class Terlihat
{
    public function __construct(private User $pengguna) {}

    public static function untuk(?User $pengguna = null): self
    {
        return new self($pengguna ?? auth()->user());
    }

    /** Peran selain satuan kerja mengawasi seluruh berkas — itu memang tugasnya. */
    public function seluruhnya(): bool
    {
        return $this->pengguna->peran !== PeranPengguna::SATKER;
    }

    private function satkerId(): ?int
    {
        return $this->pengguna->satker_id;
    }

    /* ================================================================
       REKOMENDASI
       ================================================================ */

    public function rekomendasi(): Builder
    {
        $q = Rekomendasi::query();
        if (! $this->seluruhnya()) {
            $satker = $this->satkerId();
            $q->whereHas('sasaran', fn ($s) => $s->where('sasarans.satker_id', $satker));
        }

        return $q;
    }

    public function bolehLihatRekomendasi(Rekomendasi $r): bool
    {
        return $this->seluruhnya() || $r->dituju($this->satkerId());
    }

    /**
     * Memangkas sebuah rekomendasi yang sudah dimuat, lalu mengecilkan nilai
     * tagihannya jadi bagian pengguna ini saja. Model yang sudah dipangkas
     * TIDAK BOLEH disimpan — ia hanya untuk ditampilkan.
     */
    public function pangkasRekomendasi(Rekomendasi $r): Rekomendasi
    {
        if ($this->seluruhnya()) {
            return $r;
        }

        $satker = $this->pengguna->satker;
        $semua = $r->daftarSasaran();
        $r->barisSemua = $semua;

        $baris = $semua->where('satker_id', $satker->id)->values();
        $r->setRelation('sasaran', $baris);
        $r->nilai_pulih = (int) $baris->sum('nilai');

        /* Baris yang ikut termuat lewat tindakannya dipangkas juga. */
        if ($r->relationLoaded('tindakan')) {
            $r->tindakan->each(function ($tk) use ($satker) {
                if ($tk->relationLoaded('sasaran')) {
                    $tk->setRelation('sasaran', $tk->sasaran->where('satker_id', $satker->id)->values());
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
            $r->setRelation('keputusan', $r->keputusan->filter($milikku)->values()->each(function ($x) use ($satker) {
                $x->catatan = self::teksUntuk(self::catatanUntuk($x->catatan, $satker), $satker);
                $x->catatan_satker = collect($x->catatan_satker ?? [])
                    ->filter(fn ($c) => ($c['satker_id'] ?? null) === $satker->id)->values()->all();
            }));
        }

        if ($r->relationLoaded('surat')) {
            $r->setRelation('surat', $r->surat->filter($milikku)->values()->each(function ($x) use ($satker) {
                $x->perihal = self::teksUntuk($x->perihal, $satker);
                $x->catatan = self::teksUntuk($x->catatan, $satker);
            }));
        }

        /* Riwayat aktivitas milik rekomendasi, bukan milik baris — difilter
           menurut pelakunya, dan kalimatnya disamarkan. */
        if ($r->relationLoaded('riwayat')) {
            $r->setRelation('riwayat', $r->riwayat
                ->reject(fn ($x) => self::punyaSatkerLain($x->label_aktor, $satker))
                ->values()
                ->each(fn ($x) => $x->aksi = self::teksUntuk($x->aksi, $satker)));
        }

        /* Surat pemeriksaan asli tidak pernah diberikan ke satuan kerja: satu
           surat memuat temuan seluruh satuan kerja. */
        if ($r->relationLoaded('lampiran')) {
            $r->setRelation('lampiran', $r->lampiran
                ->reject(fn ($x) => $x->surat_asli || self::punyaSatkerLain($x->label_oleh, $satker))
                ->filter($milikku)
                ->values());
        }

        return $r;
    }

    /* ================================================================
       LAPORAN
       ================================================================ */

    /**
     * Laporan yang menyangkut pengguna ini. Bagi satuan kerja: yang punya
     * baris untuknya, atau punya temuan yang terjadi di tempatnya.
     */
    public function laporan(): Builder
    {
        $q = Laporan::query();
        if ($this->seluruhnya()) {
            return $q;
        }

        $satker = $this->satkerId();

        return $q->where(function ($x) use ($satker) {
            $x->whereHas('temuan.satkers', fn ($s) => $s->where('satkers.id', $satker))
              ->orWhereHas('temuan.rekomendasi.sasaran',
                  fn ($s) => $s->where('sasarans.satker_id', $satker));
        });
    }

    /**
     * Memangkas isi sebuah laporan yang sudah dimuat. Temuan yang tidak
     * menyangkut pengguna dibuang; temuan yang menyangkutnya hanya sebagai
     * tempat kejadian dipertahankan tanpa rekomendasi.
     */
    public function pangkas(Laporan $l): Laporan
    {
        if ($this->seluruhnya()) {
            return $l;
        }

        $satker = $this->pengguna->satker;

        $temuan = $l->temuan->map(function ($t) use ($satker) {
            $punyaku = $t->rekomendasi
                ->filter(fn ($r) => $r->dituju($satker->id))
                ->map(fn ($r) => $this->pangkasRekomendasi($r))
                ->values();

            if ($punyaku->isEmpty() && ! $t->mengenai($satker->id)) {
                return null;
            }

            $t->setRelation('rekomendasi', $punyaku);
            $t->setRelation('satkers', $t->satkers->where('id', $satker->id)->values()
                ->whenEmpty(fn () => collect([$satker])));
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
        $satker = $this->satkerId();

        return $l->temuan->contains(fn ($t) => $t->mengenai($satker)
            || $t->rekomendasi->contains(fn ($r) => $r->dituju($satker)));
    }

    /** Daftar laporan yang sudah dipangkas isinya, siap ditampilkan. */
    public function daftarLaporan(array $muat = []): Collection
    {
        $bawaan = ['temuan.satkers', 'temuan.kategori', 'temuan.kategoriIntern',
            'temuan.rekomendasi.sasaran.satker', 'temuan.rekomendasi.tindakan.bentuk',
            'temuan.rekomendasi.pemulihan', 'temuan.rekomendasi.tolakanBpk',
            'temuan.rekomendasi.permintaanDokumen.item', 'temuan.rekomendasi.riwayat',
            'lampiran'];

        /* Urutan dasarnya urutan pencatatan, sama dengan prototipe — urutan
           yang seri pada tiap penyortiran jatuh ke sini. */
        return $this->laporan()
            ->with(array_unique(array_merge($bawaan, $muat)))
            ->orderBy('id')
            ->get()
            /* Induk tiap temuan dan rekomendasi dihubungkan ke model yang sudah
               dimuat (27 Sep). Tanpa ini tiap rekomendasi yang bertanya
               "laporanku jenis apa" memuat ulang temuan dan laporannya satu
               per satu — 66 kueri tambahan di Daftar laporan data contoh. */
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

    /* ================================================================
       PENYAMARAN KALIMAT
       ================================================================ */

    /** @var list<string>|null */
    private static ?array $nama = null;

    /**
     * Daftar nama disimpan selama proses. Sejak unit kerja bisa ditambah lewat
     * Data master (18 Sep), daftarnya dilepas tiap kali unit kerja berubah —
     * unit kerja baru harus ikut disamarkan sejak kalimat pertama yang
     * menyebutnya.
     */
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

    /** Nama itu milik satuan kerja lain (bukan "Setba", bukan saya). */
    public static function punyaSatkerLain(?string $nama, Satker $saya): bool
    {
        $nama = trim((string) $nama);

        return $nama !== '' && in_array($nama, self::semuaNama(), true)
            && $nama !== $saya->nama && $nama !== $saya->nama_pendek;
    }

    /**
     * Deret nama satuan kerja di dalam kalimat diganti: nama saya kalau saya
     * termasuk, selain itu "satuan kerja yang dituju". Kalimat "Rekomendasi
     * dikirim ke Balai Wil. I Medan, Politeknik PU" tidak boleh memberi tahu
     * Medan siapa lagi yang kebagian.
     */
    public static function teksUntuk(?string $teks, Satker $saya): ?string
    {
        if (! $teks) {
            return $teks;
        }
        $pola = implode('|', array_map(fn ($x) => preg_quote($x, '/'), self::semuaNama()));
        $deret = "/(?:{$pola})(?:\\s*(?:,|dan)\\s*(?:{$pola}))*/u";

        return preg_replace_callback($deret, function ($m) use ($saya) {
            return str_contains($m[0], $saya->nama) || str_contains($m[0], $saya->namaPendek())
                ? $saya->namaPendek()
                : 'satuan kerja yang dituju';
        }, $teks);
    }

    /** Buang penggalan " · " yang berawalan nama satuan kerja lain. */
    public static function catatanUntuk(?string $teks, Satker $saya): string
    {
        return collect(explode(' · ', (string) $teks))
            ->reject(function ($bagian) use ($saya) {
                $b = mb_strpos($bagian, ': ');

                return $b !== false && self::punyaSatkerLain(mb_substr($bagian, 0, $b), $saya);
            })
            ->join(' · ');
    }
}
