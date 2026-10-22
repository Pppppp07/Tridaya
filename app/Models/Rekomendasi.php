<?php

namespace App\Models;

use App\Enums\HasilTelaah;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Enums\SumberLaporan;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Unit yang dipantau. Rekomendasi tidak menempuh proses: keadaannya dihitung dari baris tindak
 * lanjut (`tindakans` → `sasarans`). Yang disimpan hanya `status` (BPK lama, dan status LHA dari
 * surat Inspektorat).
 */
class Rekomendasi extends Model
{
    protected $fillable = [
        'temuan_id', 'kode', 'ref_lhp', 'nomor_urut', 'uraian', 'sifat_id',
        'nilai_pulih', 'rencana_angsur', 'kunci_angsur',
        'tenggat_jawab', 'target_selesai', 'catatan',
        'status',
    ];

    protected $casts = [
        'status'             => StatusTindakLanjut::class,
        'tenggat_jawab'      => 'date',
        'target_selesai'     => 'date',
        'kunci_angsur'       => 'boolean',
    ];

    public function temuan()   { return $this->belongsTo(Temuan::class); }
    public function sifat()    { return $this->belongsTo(Referensi::class, 'sifat_id'); }

    public function tindakan() { return $this->hasMany(Tindakan::class)->orderBy('urutan')->orderBy('id'); }

    /** Seluruh baris penugasan, lintas bentuk tindak lanjut. */
    public function sasaran()
    {
        return $this->hasManyThrough(Sasaran::class, Tindakan::class,
            'rekomendasi_id', 'tindakan_id', 'id', 'id')
            ->orderBy('tindakans.urutan')->orderBy('tindakans.id')->orderBy('sasarans.id');
    }

    public function keputusan()         { return $this->hasMany(KeputusanVerifikasi::class)->orderBy('id'); }
    public function lampiran()          { return $this->hasMany(Lampiran::class)->orderBy('id'); }
    public function riwayat()           { return $this->hasMany(RiwayatBerkas::class)->orderBy('waktu')->orderBy('id'); }
    public function surat()             { return $this->hasMany(Surat::class)->orderBy('id'); }
    public function tanggapan()         { return $this->hasMany(Tanggapan::class)->orderBy('tanggal')->orderBy('id'); }
    public function permintaanDokumen() { return $this->hasMany(PermintaanDokumen::class)->orderBy('id'); }
    public function pemulihan()         { return $this->hasMany(Pemulihan::class)->orderBy('tanggal')->orderBy('id'); }
    public function pengembalian()      { return $this->hasMany(Pengembalian::class)->orderBy('id'); }
    public function telaah()            { return $this->hasMany(Telaah::class)->orderBy('id'); }
    public function tolakanBpk()        { return $this->hasMany(TolakanBpk::class)->orderBy('id'); }

    /* ---- Penomoran resmi ---- */

    /** Ref LHP — disalin dari surat; bila kosong dipakai rakitannya. */
    public function refLhp(): string
    {
        $tersimpan = trim((string) $this->ref_lhp);
        if ($tersimpan !== '') {
            return $tersimpan;
        }

        return collect([$this->temuan?->nomor_pada_surat, $this->nomor_urut])
            ->filter()->join('.');
    }

    /** Ref IDT = TahunLHP . NomorPendekLHP . RefLHP, mis. 2025.12.b.II.4.4.f */
    public function refIdt(): string
    {
        $lap = $this->temuan?->laporan;
        if (! $lap) {
            return $this->refLhp();
        }

        $tahun = optional($lap->tgl_surat ?? $lap->tgl_terima)->format('Y');
        $noSurat = explode('/', (string) $lap->nomor)[0] ?? '';

        return collect([$tahun, $noSurat, $this->refLhp()])->filter()->join('.');
    }

    public function jenis(): SumberLaporan
    {
        return $this->temuan?->laporan?->sumber ?? SumberLaporan::LHP;
    }

    /* ---- Baris ---- */

    /**
     * Seluruh baris sebelum dipangkas untuk satuan kerja (diisi Terlihat::pangkasRekomendasi(),
     * bukan atribut Eloquent). Keadaan rekomendasi tetap dihitung dari seluruh barisnya.
     */
    public ?Collection $barisSemua = null;

    /** Baris yang boleh ditampilkan kepada pembacanya. */
    public function daftarSasaran(): Collection
    {
        return $this->relationLoaded('sasaran')
            ? $this->getRelation('sasaran')
            : $this->sasaran()->get();
    }

    /** Seluruh baris, untuk hitungan keadaan rekomendasi seutuhnya. */
    public function semuaBaris(): Collection
    {
        return $this->barisSemua ?? $this->daftarSasaran();
    }

    /** Hasil impor data historis. Impor selalu membuat rekomendasinya utuh, tidak menempel ke yang ada. */
    public function historis(): bool
    {
        return $this->semuaBaris()->contains(fn (Sasaran $s) => $s->historis());
    }

    /** Kolom Keterangan daftar Rekomendasi; ikut dicari, jadi "historis" di kotak cari memilah daftarnya. */
    public function asalData(): string
    {
        return $this->historis() ? 'Data historis' : 'Data sistem';
    }

    /**
     * Baris yang boleh dibaca peran ini: satuan kerja hanya barisnya sendiri, pimpinan baris Satker
     * yang dipimpinnya. `$satkerId` = satu Satker (angka) atau beberapa (daftar).
     */
    public function barisTerlihat(PeranPengguna $peran, int|array|null $satkerId): Collection
    {
        return $peran->terbatasSatker()
            ? $this->daftarSasaran()->whereIn('satker_id', (array) $satkerId)->values()
            : $this->daftarSasaran();
    }

    /** Satker ini termasuk cakupan (satu angka atau daftar). */
    private static function dalam(int|array|null $satkerId, $id): bool
    {
        return $id !== null && in_array((int) $id, array_map('intval', (array) $satkerId), true);
    }

    /** Satuan kerja yang terlihat, urut kemunculan pertamanya. */
    public function satkerTampil(PeranPengguna $peran, int|array|null $satkerId): \Illuminate\Support\Collection
    {
        return $this->barisTerlihat($peran, $satkerId)->map->satker->filter()->unique('id')->values();
    }

    /** "—", nama pendek, atau "N satuan kerja". */
    public function sebutSatker(PeranPengguna $peran, int|array|null $satkerId): string
    {
        $d = $this->satkerTampil($peran, $satkerId);

        return match (true) {
            $d->isEmpty()     => '—',
            $d->count() === 1 => $d->first()->namaPendek(),
            default           => $d->count().' satuan kerja',
        };
    }

    public function dituju(int|array|null $satkerId): bool
    {
        return $satkerId !== null && $this->daftarSasaran()->contains(fn ($x) => self::dalam($satkerId, $x->satker_id));
    }

    /* ---- Posisi (dari baris) ---- */

    public function semuaTuntas(): bool
    {
        $s = $this->semuaBaris();

        return $s->isNotEmpty() && $s->every(fn ($x) => $x->tuntas());
    }

    /** Posisi baris PALING BELAKANG — satu yang belum menjawab menahan seluruhnya. */
    public function posisiRek(): PosisiBerkas
    {
        return PosisiBerkas::palingBelakang($this->semuaBaris()->map->pos());
    }

    /** Posisi satu satuan kerja: yang paling belakang dari barisnya. */
    public function posisiSatker(int|array $satkerId): PosisiBerkas
    {
        $baris = $this->daftarSasaran()->whereIn('satker_id', (array) $satkerId);

        return $baris->isEmpty()
            ? PosisiBerkas::TUNTAS
            : PosisiBerkas::palingBelakang($baris->map->pos());
    }

    /** Satuan kerja yang terburuk hasilnya menang: satu belum, ia masih belum. */
    public function hasilSatker(int|array $satkerId): HasilTelaah
    {
        $baris = $this->daftarSasaran()->whereIn('satker_id', (array) $satkerId);

        return $baris->isNotEmpty() && $baris->every(fn ($x) => $x->hasil === HasilTelaah::M)
            ? HasilTelaah::M
            : HasilTelaah::BM;
    }

    /* ---- Status: dua sumbu ---- */

    /** Memadai bila seluruh baris memadai, dibaca dari baris (bukan surat CHV). */
    public function keadaanUnor(): HasilTelaah
    {
        $s = $this->semuaBaris();

        return $s->isNotEmpty() && $s->every(fn ($x) => $x->hasil === HasilTelaah::M)
            ? HasilTelaah::M
            : HasilTelaah::BM;
    }

    /**
     * Keadaan yang ditampilkan: satuan kerja membaca keadaan tindak lanjutnya sendiri, peran lain
     * keadaan seluruh rekomendasi.
     */
    public function keadaanUntuk(int|array|null $satkerId): HasilTelaah
    {
        return $satkerId ? $this->hasilSatker($satkerId) : $this->keadaanUnor();
    }

    /** Status SIPTL yang ditampilkan; null = belum ada yang diunggah. */
    public function statusBpkUntuk(int|array|null $satkerId): ?StatusTindakLanjut
    {
        return $satkerId
            ? self::rangkumBpk($this->daftarSasaran()->whereIn('satker_id', (array) $satkerId)->map->statusSiptl())
            : $this->statusRek();
    }

    /**
     * Rangkuman status SIPTL: yang paling belakang menentukan (BT 3, BS 2, SS 1). Baris yang belum
     * diunggah tidak berstatus: sebagian selesai tapi sisanya belum naik = BS. Null = belum ada
     * yang diunggah.
     */
    public static function rangkumBpk(iterable $semua): ?StatusTindakLanjut
    {
        $peringkat = ['SS' => 1, 'BS' => 2, 'BT' => 3];
        $puncak = 0;
        $adaNaik = false;
        foreach ($semua as $s) {
            $adaNaik = $adaNaik || $s !== null;
            $puncak = max($puncak, $s ? $peringkat[$s->value] : 2);
        }

        return match (true) {
            ! $adaNaik    => null,
            $puncak === 3 => StatusTindakLanjut::BT,
            $puncak === 2 => StatusTindakLanjut::BS,
            default       => StatusTindakLanjut::SS,
        };
    }

    /** Rangkuman SIPTL dari barisnya; null = belum ada yang diunggah. */
    public function statusRek(): ?StatusTindakLanjut
    {
        return self::rangkumBpk($this->semuaBaris()->map->statusSiptl());
    }

    public function perluUnggahSiptl(): bool
    {
        $jenis = $this->jenis();

        return $this->semuaBaris()->contains(fn ($x) => $x->perluUnggah($jenis));
    }

    /** BPK tidak mengirim pemberitahuan apa pun — Setba yang berulang kali mengecek. */
    public function perluCekBpk(): bool
    {
        $jenis = $this->jenis();

        return $this->semuaBaris()->contains(fn ($x) => $x->perluCek($jenis));
    }

    /** Ditolak BPK dan menunggu dikirim ulang ke satuan kerjanya. */
    public function perluKirimUlangBpk(): bool
    {
        $jenis = $this->jenis();

        return $this->semuaBaris()->contains(fn ($x) => $x->perluKirimUlang($jenis));
    }

    public function kerjaSiptl(): bool
    {
        return $this->perluUnggahSiptl() || $this->perluCekBpk()
            || $this->perluKirimUlangBpk();
    }

    /** Tidak ada lagi yang bisa dikerjakan siapa pun. */
    public function beres(): bool
    {
        if (! $this->jenis()->melewatiSiptl()) {
            return $this->semuaTuntas();
        }

        return $this->statusRek() === StatusTindakLanjut::SS;
    }

    /* ---- Uang dan dokumen ---- */

    /** Seluruh nilai baris. Satuan kerja yang membaca rekomendasi terpangkas mendapat bagiannya. */
    public function nilaiRek(): int
    {
        return (int) $this->daftarSasaran()->sum('nilai');
    }

    public function nilaiSatker(int|array $satkerId, ?int $tindakanId = null): int
    {
        return (int) $this->daftarSasaran()
            ->filter(fn ($x) => self::dalam($satkerId, $x->satker_id) && (! $tindakanId || $x->tindakan_id === $tindakanId))
            ->sum('nilai');
    }

    /**
     * Butir milik satu satuan kerja dan satu bentuk tindak lanjut; butir tanpa baris berlaku untuk
     * semuanya.
     */
    public function milik($butir, int|array|null $satkerId, ?int $tindakanId): bool
    {
        if (! $butir->sasaran_id) {
            return true;
        }
        $baris = $this->daftarSasaran()->firstWhere('id', $butir->sasaran_id);
        if (! $baris) {
            return true;
        }

        return (! $satkerId || self::dalam($satkerId, $baris->satker_id))
            && (! $tindakanId || $baris->tindakan_id === $tindakanId);
    }

    public function totalTolakan(int|array|null $satkerId = null, ?int $tindakanId = null): int
    {
        return (int) $this->tolakanBpk
            ->filter(fn ($x) => $this->milik($x, $satkerId, $tindakanId))->sum('nilai');
    }

    /** Uang yang sudah masuk, dikurangi yang ditolak BPK. */
    public function totalSetor(int|array|null $satkerId = null, ?int $tindakanId = null): int
    {
        $masuk = (int) $this->pemulihan
            ->filter(fn ($x) => $this->milik($x, $satkerId, $tindakanId))->sum('nilai');

        return max(0, $masuk - $this->totalTolakan($satkerId, $tindakanId));
    }

    /** @return array{target:int, masuk:int, sisa:int, persen:float}|null */
    public function progresDana(int|array|null $satkerId = null, ?int $tindakanId = null): ?array
    {
        $target = $satkerId ? $this->nilaiSatker($satkerId, $tindakanId) : (int) $this->nilai_pulih;
        if (! $target) {
            return null;
        }
        $masuk = $this->totalSetor($satkerId, $tindakanId);

        return ['target' => $target, 'masuk' => $masuk, 'sisa' => max(0, $target - $masuk),
            'persen' => min(100, $masuk / $target * 100)];
    }

    public function permintaanUntuk(int|array|null $satkerId = null, ?int $tindakanId = null): \Illuminate\Support\Collection
    {
        return $this->permintaanDokumen->filter(fn ($p) => $this->milik($p, $satkerId, $tindakanId))->values();
    }

    /** @return array{ada:int, dari:int}|null */
    public function progresDok(int|array|null $satkerId = null, ?int $tindakanId = null): ?array
    {
        $butir = $this->permintaanUntuk($satkerId, $tindakanId)->flatMap->item;

        return $butir->isEmpty() ? null
            : ['ada' => $butir->where('terpenuhi', true)->count(), 'dari' => $butir->count()];
    }

    /** @return array{rencana:int, sudah:int, sisa:int, kunci:bool}|null */
    public function rencanaAngsur(): ?array
    {
        $n = (int) $this->rencana_angsur;
        if (! $n) {
            return null;
        }
        $sudah = $this->pemulihan->count();

        return ['rencana' => $n, 'sudah' => $sudah, 'sisa' => max(0, $n - $sudah), 'kunci' => (bool) $this->kunci_angsur];
    }

    public function nilaiDiakuiItjen(): int
    {
        return (int) $this->daftarSasaran()->sum(fn ($x) => $x->nilaiDiakuiItjen());
    }

    public function nilaiDiakuiBpk(): int
    {
        return (int) $this->daftarSasaran()->sum(fn ($x) => $x->nilaiDiakuiBpk());
    }

    /** Sisa nilai: yang belum DIAKUI — bukan uang yang belum masuk kas. */
    public function sisaNilaiBpk(): int   { return max(0, $this->nilaiRek() - $this->nilaiDiakuiBpk()); }
    public function sisaNilaiItjen(): int { return max(0, $this->nilaiRek() - $this->nilaiDiakuiItjen()); }

    /* ---- Tenggat ---- */

    /** Hari dari tanggal $a sampai hari ini (positif = sudah lewat). */
    public static function selisih(?CarbonInterface $a, ?CarbonInterface $b = null): ?int
    {
        if (! $a) {
            return null;
        }
        $b ??= now();

        return (int) round($a->copy()->startOfDay()->diffInDays($b->copy()->startOfDay(), false));
    }

    /**
     * Tenggat tidak berlaku lagi bila BPK sudah memutus atau semua tindak lanjut selesai diperiksa.
     */
    public function tanpaTenggat(): bool
    {
        return $this->status === StatusTindakLanjut::SS || $this->semuaTuntas();
    }

    public function telatTenggat(): bool
    {
        return ! $this->tanpaTenggat() && $this->tenggat_jawab
            && $this->tenggat_jawab->copy()->startOfDay()->lt(now()->startOfDay());
    }

    /** Berapa hari tenggat menjawab terlewat (0 bila tidak). */
    public function lewatTenggat(): int
    {
        return $this->telatTenggat() ? (int) self::selisih($this->tenggat_jawab) : 0;
    }

    /** Hari lewat batas perbaikan selama perbaikannya belum dikirim. */
    public function hariLewatPerbaikan(): int
    {
        return (int) $this->daftarSasaran()
            ->filter(fn ($x) => $x->batas_perbaikan
                && $x->batas_perbaikan->copy()->startOfDay()->lt(now()->startOfDay())
                && in_array($x->pos(), [PosisiBerkas::SATKER, PosisiBerkas::SETBA_KEMBALI], true))
            ->map(fn ($x) => self::selisih($x->batas_perbaikan))
            ->max();
    }

    /** Terlambat: tenggat menjawab terlewat, ATAU batas perbaikan terlewat. */
    public function telat(): bool
    {
        return $this->telatTenggat() || $this->hariLewatPerbaikan() > 0;
    }

    /** Sisa hari menuju tenggat; negatif sudah lewat, 999 tanpa tenggat. */
    public function sisaHari(): int
    {
        $n = self::selisih($this->tenggat_jawab);

        return $n === null ? 999 : -$n;
    }

    /** Masih berjalan, dan tenggatnya sudah lewat atau tinggal sepekan. */
    public function perluPerhatian(): bool
    {
        if ($this->tanpaTenggat()) {
            return false;
        }
        if ($this->telat()) {
            return true;
        }
        $sisa = $this->sisaHari();

        return $sisa >= 0 && $sisa <= 7;
    }

    /** Berapa hari sejak berkas ini terakhir bergerak. */
    public function diamnya(): int
    {
        $n = self::selisih($this->riwayat->last()?->waktu);

        return $n && $n > 0 ? $n : 0;
    }

    /** Skor kemendesakan untuk urutan bawaan daftar. */
    public function urgensi(): float
    {
        $skor = 0;
        $lewat = max($this->lewatTenggat(), $this->hariLewatPerbaikan());
        if ($lewat > 0) {
            $skor += 4000 + min($lewat, 400);
        } else {
            $sisa = $this->sisaHari();
            if ($sisa <= 7) {
                $skor += 2000 + (7 - $sisa) * 20;
            } elseif ($sisa <= 21) {
                $skor += 600;
            }
        }
        $diam = $this->diamnya();
        if ($diam > 21) {
            $skor += 900 + min($diam, 200);
        } elseif ($diam > 10) {
            $skor += 400;
        }
        $dana = $this->progresDana();
        if ($dana && $dana['sisa'] > 0) {
            $skor += min($dana['sisa'] / 5e6, 150);
        }
        $dok = $this->progresDok();
        if ($dok) {
            $skor += ($dok['dari'] - $dok['ada']) * 8;
        }

        return $skor;
    }

    /** Rencana aksi: LHA 30 hari kerja, LHP 60 hari kalender. */
    public static function hitungTenggat(CarbonInterface $mulai, SumberLaporan $sumber): CarbonInterface
    {
        $t = $mulai->copy();
        if (! $sumber->pakaiHariKerja()) {
            return $t->addDays($sumber->hariTenggat());
        }
        $sisa = $sumber->hariTenggat();
        while ($sisa > 0) {
            $t->addDay();
            if (! $t->isWeekend()) {
                $sisa--;
            }
        }

        return $t;
    }

    /** Rencana aksi satu satuan kerja: tanggal paling awal di antara bentuk tindak lanjutnya. */
    public function renaksiUntuk(int|array|null $satkerId = null): ?CarbonInterface
    {
        if ($satkerId === null) {
            return $this->tenggat_jawab;
        }

        return $this->tindakan
            ->filter(fn ($tk) => $tk->sasaran->contains(fn ($x) => self::dalam($satkerId, $x->satker_id)))
            ->map->tgl_renaksi->filter()->sort()->first() ?? $this->tenggat_jawab;
    }

    /* ---- Meja: "Perlu saya kerjakan" ---- */

    /**
     * Dibaca dari tiap baris, supaya satuan kerja yang belum menjawab tidak menyembunyikan berkas
     * yang sudah sampai.
     */
    public function diMeja(PeranPengguna $peran, int|array|null $satkerId = null): bool
    {
        $dipegang = fn (PeranPengguna $p) => $this->semuaBaris()->contains(fn ($x) => $x->pemegang() === $p);

        return match ($peran) {
            PeranPengguna::SATKER      => $satkerId !== null && $this->dituju($satkerId)
                                          && $this->posisiSatker($satkerId) === PosisiBerkas::SATKER,
            PeranPengguna::UKI         => $dipegang(PeranPengguna::UKI),
            PeranPengguna::INSPEKTORAT => $dipegang(PeranPengguna::INSPEKTORAT),
            /* Setba juga mengetik putusan Inspektorat dari kertas dan urusan SIPTL. */
            PeranPengguna::SETBA       => $dipegang(PeranPengguna::SETBA) || $dipegang(PeranPengguna::INSPEKTORAT)
                                          || $this->kerjaSiptl(),
            default                    => false,
        };
    }

    /* ---- Kesimpulan ---- */

    /**
     * Status SIPTL temuan atau laporan. Rekomendasi tanpa status SIPTL tidak dihitung, tapi membuat
     * SS jadi BS (belum seluruhnya). Null = belum ada yang diunggah.
     */
    public static function simpulkan(\Illuminate\Support\Collection $daftar): ?StatusTindakLanjut
    {
        $s = $daftar->map(fn ($r) => $r->statusRek());
        $naik = $s->filter();
        if ($naik->isEmpty()) {
            return null;
        }
        $sebagian = $naik->count() < $s->count();
        if ($naik->every(fn ($x) => $x === StatusTindakLanjut::SS)) {
            return $sebagian ? StatusTindakLanjut::BS : StatusTindakLanjut::SS;
        }
        if ($naik->contains(fn ($x) => in_array($x, [StatusTindakLanjut::SS, StatusTindakLanjut::BS], true))) {
            return StatusTindakLanjut::BS;
        }

        return StatusTindakLanjut::BT;
    }
}
