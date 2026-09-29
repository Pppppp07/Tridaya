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
 * Unit yang dipantau. SOP mewajibkan Daftar Kompilasi Monitoring memuat uraian
 * rekomendasi, nilai keuangan dalam rekomendasi, dan hasil pemantauan status —
 * ketiganya melekat di sini, bukan di temuan.
 *
 * Rekomendasi TIDAK menempuh proses. Yang berjalan tindak lanjut tiap satuan
 * kerja (`tindakans` → `sasarans`); keadaan rekomendasi seutuhnya selalu
 * dihitung dari barisnya, tidak disimpan. Satu-satunya yang tetap disimpan di
 * sini `status`: status BPK catatan lama, dan status LHA yang ditetapkan surat
 * Inspektorat.
 *
 * Nama-nama method mengikuti pembantu prototipe (`statusRek`, `posisiRek`,
 * `hariLewatPerbaikan`, ...) supaya aturan yang sama bisa ditelusuri di kedua
 * sisi tanpa kamus.
 */
class Rekomendasi extends Model
{
    protected $fillable = [
        'temuan_id', 'kode', 'ref_lhp', 'nomor_urut', 'uraian', 'sifat_id',
        'nilai_pulih', 'rencana_angsur', 'kunci_angsur',
        'tenggat_jawab', 'target_selesai', 'catatan',
        'status', 'alasan_td_id', 'catatan_td',
        'siptl_tanggal', 'siptl_status', 'siptl_catatan', 'siptl_dicatat_pada',
    ];

    protected $casts = [
        'status'             => StatusTindakLanjut::class,
        'siptl_status'       => StatusTindakLanjut::class,
        'tenggat_jawab'      => 'date',
        'target_selesai'     => 'date',
        'siptl_tanggal'      => 'date',
        'siptl_dicatat_pada' => 'date',
        'kunci_angsur'       => 'boolean',
    ];

    public function temuan()   { return $this->belongsTo(Temuan::class); }
    public function sifat()    { return $this->belongsTo(Referensi::class, 'sifat_id'); }
    public function alasanTd() { return $this->belongsTo(Referensi::class, 'alasan_td_id'); }

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

    /* ================================================================
       PENOMORAN RESMI
       ================================================================ */

    /**
     * Ref LHP — disalin apa adanya dari suratnya, tidak dirakit. Kalau belum
     * diisi, rakitannya dipakai supaya berkas lama tetap punya kode.
     */
    public function refLhp(): string
    {
        $tersimpan = trim((string) $this->ref_lhp);
        if ($tersimpan !== '') {
            return $tersimpan;
        }

        return collect([$this->temuan?->nomor_pada_surat, $this->nomor_urut])
            ->filter()->join('.');
    }

    /**
     * Ref IDT — dirakit, dan rumusnya tetap: TahunLHP . NomorPendekLHP . RefLHP.
     * 2025 + "12.b" + "II.4.4.f" menghasilkan 2025.12.b.II.4.4.f
     */
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

    /* ================================================================
       BARIS
       ================================================================ */

    /**
     * Seluruh baris sebelum dipangkas untuk satuan kerja. Diisi
     * `Terlihat::pangkasRekomendasi()`; bukan atribut Eloquent, jadi tidak
     * pernah ikut tersimpan atau terkirim ke tampilan.
     *
     * Keadaan rekomendasi seutuhnya — sudah selesai atau belum, status
     * rangkumannya, tenggatnya masih berlaku atau tidak — tetap dihitung dari
     * seluruh barisnya, sama seperti prototipe. Yang dipangkas hanya yang
     * DITAMPILKAN.
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

    /** Baris yang boleh dibaca peran ini: satuan kerja hanya barisnya sendiri. */
    public function barisTerlihat(PeranPengguna $peran, ?int $satkerId): Collection
    {
        return $peran === PeranPengguna::SATKER
            ? $this->daftarSasaran()->where('satker_id', $satkerId)->values()
            : $this->daftarSasaran();
    }

    /** Satuan kerja yang terlihat, urut kemunculan pertamanya. */
    public function satkerTampil(PeranPengguna $peran, ?int $satkerId): \Illuminate\Support\Collection
    {
        return $this->barisTerlihat($peran, $satkerId)->map->satker->filter()->unique('id')->values();
    }

    /** "—", nama pendek, atau "N satuan kerja". */
    public function sebutSatker(PeranPengguna $peran, ?int $satkerId): string
    {
        $d = $this->satkerTampil($peran, $satkerId);

        return match (true) {
            $d->isEmpty()     => '—',
            $d->count() === 1 => $d->first()->namaPendek(),
            default           => $d->count().' satuan kerja',
        };
    }

    public function dituju(?int $satkerId): bool
    {
        return $satkerId !== null && $this->daftarSasaran()->contains('satker_id', $satkerId);
    }

    /* ================================================================
       POSISI — dibaca dari baris
       ================================================================ */

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

    /**
     * Posisi satu satuan kerja: yang paling belakang dari barisnya. Tidak punya
     * baris berarti tidak dituju — tidak ada yang ditunggu darinya.
     */
    public function posisiSatker(int $satkerId): PosisiBerkas
    {
        $baris = $this->daftarSasaran()->where('satker_id', $satkerId);

        return $baris->isEmpty()
            ? PosisiBerkas::TUNTAS
            : PosisiBerkas::palingBelakang($baris->map->pos());
    }

    /** Satuan kerja yang terburuk hasilnya menang: satu belum, ia masih belum. */
    public function hasilSatker(int $satkerId): HasilTelaah
    {
        $baris = $this->daftarSasaran()->where('satker_id', $satkerId);

        return $baris->isNotEmpty() && $baris->every(fn ($x) => $x->hasil === HasilTelaah::M)
            ? HasilTelaah::M
            : HasilTelaah::BM;
    }

    /* ================================================================
       STATUS — dua sumbu
       ================================================================ */

    /**
     * Keadaan menurut BPSDM: memadai kalau SELURUH baris memadai. Dibaca dari
     * baris, bukan dari surat CHV — lembar pemantauan mereka cuma mencatat
     * nomor CHV pada 47 dari 123 rekomendasi.
     */
    public function keadaanUnor(): HasilTelaah
    {
        $s = $this->semuaBaris();

        return $s->isNotEmpty() && $s->every(fn ($x) => $x->hasil === HasilTelaah::M)
            ? HasilTelaah::M
            : HasilTelaah::BM;
    }

    /**
     * Keadaan yang DITAMPILKAN (27 Sep): satuan kerja (`$satkerId` terisi)
     * membaca keadaan tindak lanjutnya sendiri, peran lain keadaan seluruh
     * rekomendasi. Rekomendasi tetap belum memadai selama satuan kerja lain
     * belum beres, tapi itu hitungan Setba dan Inspektorat — kepada satuan kerja
     * yang bagiannya sudah memadai ia terbaca seperti kerjanya ditolak. Padanan
     * keadaanTampil di prototipe.
     */
    public function keadaanUntuk(?int $satkerId): HasilTelaah
    {
        return $satkerId ? $this->hasilSatker($satkerId) : $this->keadaanUnor();
    }

    /** Status BPK yang DITAMPILKAN — padanan statusBpkTampil. */
    public function statusBpkUntuk(?int $satkerId): StatusTindakLanjut
    {
        return $satkerId
            ? self::rangkumBpk($this->daftarSasaran()->where('satker_id', $satkerId)
                ->map(fn ($x) => $x->siptl_tanggal ? $x->status_bpk : null))
            : $this->statusRek();
    }

    /**
     * Rangkuman sederet status BPK: yang paling belakang menentukan. Peringkat
     * dari kolom `Rank Status SiPTL` lembar pemantauan — SS 1, BS 2, BT 3; TD
     * tertutup seperti SS, dan rekomendasinya TD hanya kalau seluruhnya TD.
     *
     * @param  iterable<StatusTindakLanjut|null>  $semua
     */
    public static function rangkumBpk(iterable $semua): StatusTindakLanjut
    {
        $peringkat = ['SS' => 1, 'TD' => 1, 'BS' => 2, 'BT' => 3];
        $puncak = 0;
        $semuaTd = true;
        $ada = false;
        foreach ($semua as $s) {
            $ada = true;
            $kode = $s?->value ?? 'BT';
            $puncak = max($puncak, $peringkat[$kode] ?? 3);
            $semuaTd = $semuaTd && $kode === 'TD';
        }
        if (! $ada || $puncak === 3) {
            return StatusTindakLanjut::BT;
        }
        if ($puncak === 2) {
            return StatusTindakLanjut::BS;
        }

        return $semuaTd ? StatusTindakLanjut::TD : StatusTindakLanjut::SS;
    }

    /**
     * Status BPK rekomendasi, dihitung dari barisnya. Selama belum satu baris
     * pun berstatus, status tersimpan yang berlaku. Baris yang belum diunggah
     * terhitung BT — BPK belum melihat apa pun darinya.
     */
    public function statusRek(): StatusTindakLanjut
    {
        $baris = $this->semuaBaris();
        if (! $baris->contains(fn ($x) => $x->status_bpk !== null)) {
            return $this->status ?? StatusTindakLanjut::BT;
        }

        return self::rangkumBpk($baris->map(fn ($x) => $x->status_bpk));
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

    /**
     * Tidak ada lagi yang bisa dikerjakan siapa pun. LHP berakhir di putusan
     * BPK; LHA tidak pernah sampai ke sana.
     */
    public function beres(): bool
    {
        if (! $this->jenis()->melewatiSiptl()) {
            return $this->semuaTuntas();
        }

        return in_array($this->statusRek(), [StatusTindakLanjut::SS, StatusTindakLanjut::TD], true);
    }

    /* ================================================================
       UANG DAN DOKUMEN
       ================================================================ */

    /** Seluruh nilai baris. Satuan kerja yang membaca rekomendasi terpangkas mendapat bagiannya. */
    public function nilaiRek(): int
    {
        return (int) $this->daftarSasaran()->sum('nilai');
    }

    public function nilaiSatker(int $satkerId, ?int $tindakanId = null): int
    {
        return (int) $this->daftarSasaran()
            ->filter(fn ($x) => $x->satker_id === $satkerId && (! $tindakanId || $x->tindakan_id === $tindakanId))
            ->sum('nilai');
    }

    /**
     * Butir milik satu satuan kerja dan satu bentuk tindak lanjut. Butir tanpa
     * baris berlaku untuk semuanya — catatan lama.
     */
    public function milik($butir, ?int $satkerId, ?int $tindakanId): bool
    {
        if (! $butir->sasaran_id) {
            return true;
        }
        $baris = $this->daftarSasaran()->firstWhere('id', $butir->sasaran_id);
        if (! $baris) {
            return true;
        }

        return (! $satkerId || $baris->satker_id === $satkerId)
            && (! $tindakanId || $baris->tindakan_id === $tindakanId);
    }

    public function totalTolakan(?int $satkerId = null, ?int $tindakanId = null): int
    {
        return (int) $this->tolakanBpk
            ->filter(fn ($x) => $this->milik($x, $satkerId, $tindakanId))->sum('nilai');
    }

    /** Uang yang sudah masuk, dikurangi yang ditolak BPK. */
    public function totalSetor(?int $satkerId = null, ?int $tindakanId = null): int
    {
        $masuk = (int) $this->pemulihan
            ->filter(fn ($x) => $this->milik($x, $satkerId, $tindakanId))->sum('nilai');

        return max(0, $masuk - $this->totalTolakan($satkerId, $tindakanId));
    }

    /** @return array{target:int, masuk:int, sisa:int, persen:float}|null */
    public function progresDana(?int $satkerId = null, ?int $tindakanId = null): ?array
    {
        $target = $satkerId ? $this->nilaiSatker($satkerId, $tindakanId) : (int) $this->nilai_pulih;
        if (! $target) {
            return null;
        }
        $masuk = $this->totalSetor($satkerId, $tindakanId);

        return ['target' => $target, 'masuk' => $masuk, 'sisa' => max(0, $target - $masuk),
            'persen' => min(100, $masuk / $target * 100)];
    }

    public function permintaanUntuk(?int $satkerId = null, ?int $tindakanId = null): \Illuminate\Support\Collection
    {
        return $this->permintaanDokumen->filter(fn ($p) => $this->milik($p, $satkerId, $tindakanId))->values();
    }

    /** @return array{ada:int, dari:int}|null */
    public function progresDok(?int $satkerId = null, ?int $tindakanId = null): ?array
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
        return (int) $this->daftarSasaran()->sum(fn ($x) => $x->nilaiDiakuiBpk($this));
    }

    /** Sisa nilai: yang belum DIAKUI — bukan uang yang belum masuk kas. */
    public function sisaNilaiBpk(): int   { return max(0, $this->nilaiRek() - $this->nilaiDiakuiBpk()); }
    public function sisaNilaiItjen(): int { return max(0, $this->nilaiRek() - $this->nilaiDiakuiItjen()); }

    /* ================================================================
       TENGGAT
       ================================================================ */

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
     * Tenggat menjawab sudah tidak berlaku: BPK sudah memutus, atau seluruh
     * tindak lanjutnya sudah selesai diperiksa. Tenggat ini milik satuan kerja.
     */
    public function tanpaTenggat(): bool
    {
        return in_array($this->status, [StatusTindakLanjut::SS, StatusTindakLanjut::TD], true)
            || $this->semuaTuntas();
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

    /**
     * Berapa hari batas perbaikan terlewat, sementara perbaikannya belum
     * dikirim — masih di satuan kerja atau di meja pemberkasan ulang Setba.
     */
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

    /**
     * Rencana aksi yang mengikat satu satuan kerja: tanggal paling awal di
     * antara bentuk tindak lanjut yang membebaninya.
     */
    public function renaksiUntuk(?int $satkerId = null): ?CarbonInterface
    {
        if ($satkerId === null) {
            return $this->tenggat_jawab;
        }

        return $this->tindakan
            ->filter(fn ($tk) => $tk->sasaran->contains('satker_id', $satkerId))
            ->map->tgl_renaksi->filter()->sort()->first() ?? $this->tenggat_jawab;
    }

    /* ================================================================
       MEJA — keranjang "Perlu saya kerjakan"
       ================================================================ */

    /**
     * Berkasnya sedang di meja peran ini. Dibaca dari TIAP baris, bukan dari
     * rangkumannya: satu satuan kerja yang belum menjawab tidak boleh
     * menyembunyikan berkas satuan kerja lain yang sudah sampai.
     */
    public function diMeja(PeranPengguna $peran, ?int $satkerId = null): bool
    {
        $dipegang = fn (PeranPengguna $p) => $this->semuaBaris()->contains(fn ($x) => $x->pemegang() === $p);

        return match ($peran) {
            PeranPengguna::SATKER      => $satkerId !== null && $this->dituju($satkerId)
                                          && $this->posisiSatker($satkerId) === PosisiBerkas::SATKER,
            PeranPengguna::UKI         => $dipegang(PeranPengguna::UKI),
            PeranPengguna::INSPEKTORAT => $dipegang(PeranPengguna::INSPEKTORAT),
            /* Setba juga mengetik putusan Inspektorat yang datang di kertas, dan
               urusan SIPTL-nya — perbuatan, bukan penguasaan berkas. */
            PeranPengguna::SETBA       => $dipegang(PeranPengguna::SETBA) || $dipegang(PeranPengguna::INSPEKTORAT)
                                          || $this->kerjaSiptl(),
            default                    => false,
        };
    }

    /* ================================================================
       KESIMPULAN
       ================================================================ */

    public static function simpulkan(\Illuminate\Support\Collection $daftar): StatusTindakLanjut
    {
        $s = $daftar->map(fn ($r) => $r->status ?? StatusTindakLanjut::BT);
        if ($s->isEmpty()) {
            return StatusTindakLanjut::BT;
        }
        $aktif = $s->reject(fn ($x) => $x === StatusTindakLanjut::TD);
        if ($aktif->isEmpty()) {
            return StatusTindakLanjut::TD;
        }
        if ($aktif->every(fn ($x) => $x === StatusTindakLanjut::SS)) {
            return StatusTindakLanjut::SS;
        }
        if ($aktif->contains(fn ($x) => in_array($x, [StatusTindakLanjut::SS, StatusTindakLanjut::BS], true))) {
            return StatusTindakLanjut::BS;
        }

        return StatusTindakLanjut::BT;
    }
}
