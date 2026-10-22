<?php

namespace App\Models;

use App\Aksi\BerkasBerubah;
use App\Enums\HasilTelaah;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Enums\SumberLaporan;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu satuan kerja pada satu bentuk tindak lanjut — yang menempuh proses: posisi, penilaian
 * UKI/Inspektorat/BPK, SIPTL, pemberkasan ulang.
 */
class Sasaran extends Model
{
    /* `siptl_tanggal` sengaja tidak fillable: dikunci sekali tercatat; penulisnya hanya
       CatatSiptl lewat forceFill. */
    protected $fillable = ['tindakan_id', 'satker_id', 'nilai',
        'posisi', 'hasil', 'hasil_uki', 'catatan',
        'status_bpk', 'catatan_bpk', 'uraian_verifikasi', 'tgl_pantau',
        'kembali_dari', 'alasan_perbaikan', 'batas_perbaikan', 'keterangan_setba', 'dokumen_diminta'];

    protected $casts = [
        'posisi'          => PosisiBerkas::class,
        'hasil'           => HasilTelaah::class,
        'hasil_uki'       => HasilTelaah::class,
        'status_bpk'      => StatusTindakLanjut::class,
        'siptl_tanggal'   => 'date',
        'tgl_pantau'      => 'date',
        'batas_perbaikan' => 'date',
        'dokumen_diminta' => 'array',
        'siptl_historis' => 'boolean',
        'tanggal_acuan_historis' => 'date',
        'nilai_diakui_itjen' => 'integer',
        'nilai_diakui_bpk' => 'integer',
    ];

    public function tindakan() { return $this->belongsTo(Tindakan::class); }
    public function satker()   { return $this->belongsTo(Satker::class); }

    public function tanggapan()         { return $this->hasMany(Tanggapan::class)->orderBy('tanggal')->orderBy('id'); }
    public function pemulihan()         { return $this->hasMany(Pemulihan::class)->orderBy('tanggal')->orderBy('id'); }
    public function permintaanDokumen() { return $this->hasMany(PermintaanDokumen::class)->orderBy('tanggal')->orderBy('id'); }
    public function pengembalian()      { return $this->hasMany(Pengembalian::class)->orderBy('tanggal')->orderBy('id'); }
    public function telaah()            { return $this->hasMany(Telaah::class)->orderBy('tanggal')->orderBy('id'); }
    public function lampiran()          { return $this->hasMany(Lampiran::class); }
    public function surat()             { return $this->hasMany(Surat::class)->orderBy('tanggal')->orderBy('id'); }
    public function tolakanBpk()        { return $this->hasMany(TolakanBpk::class)->orderBy('tanggal')->orderBy('id'); }
    public function draf()              { return $this->hasOne(DrafTanggapan::class); }

    /** Tiap perubahan penilaian, urut menurut pencatatannya. */
    public function riwayatStatus()     { return $this->hasMany(RiwayatStatus::class)->orderBy('id'); }

    /** Jalan pintas ke rekomendasinya - dipakai di banyak tempat. */
    public function rekomendasi(): ?Rekomendasi
    {
        return $this->tindakan?->rekomendasi;
    }

    /** Hasil impor data historis, bukan dicatat lewat Catat laporan baru. */
    public function historis(): bool
    {
        return $this->draf_historis_id !== null;
    }

    /* ---- Posisi ---- */

    /** Posisi baris ini; yang belum pernah bergerak masih di satuan kerja. */
    public function pos(): PosisiBerkas
    {
        return $this->posisi ?? PosisiBerkas::SATKER;
    }

    public function tuntas(): bool
    {
        return $this->pos() === PosisiBerkas::TUNTAS;
    }

    public function pemegang(): ?PeranPengguna
    {
        return $this->pos()->pemegang();
    }

    /* ---- Urusan SIPTL (hanya LHP) ---- */

    /** Snapshot historis dapat memiliki status resmi tanpa tanggal unggah yang diketahui. */
    public function sudahSiptl(): bool
    {
        return (bool) $this->siptl_tanggal || (bool) $this->siptl_historis;
    }

    /** Naik ke SIPTL begitu selesai diperiksa, tanpa menunggu satuan kerja lain. */
    public function perluUnggah(SumberLaporan $jenis): bool
    {
        return $jenis->melewatiSiptl() && $this->tuntas() && ! $this->sudahSiptl();
    }

    /**
     * Sudah diunggah dan BPK belum memutus unggahan itu. Putusan BPK dicatat sekali tiap unggahan.
     */
    public function perluCek(SumberLaporan $jenis): bool
    {
        return $jenis->melewatiSiptl() && $this->sudahSiptl()
            && ($this->status_bpk ?? StatusTindakLanjut::BT) === StatusTindakLanjut::BT;
    }

    /** BPK memutus Belum Sesuai: berkasnya dikirim kembali ke satuan kerja lalu diunggah ulang. */
    public function perluKirimUlang(SumberLaporan $jenis): bool
    {
        return $jenis->melewatiSiptl() && $this->sudahSiptl()
            && $this->status_bpk === StatusTindakLanjut::BS;
    }

    /**
     * Status di SIPTL; null = belum diunggah. BT hanya untuk yang sudah diunggah dan belum dinilai
     * BPK, supaya yang belum pernah naik tidak terhitung BT.
     */
    public function statusSiptl(): ?StatusTindakLanjut
    {
        return $this->sudahSiptl() ? ($this->status_bpk ?? StatusTindakLanjut::BT) : null;
    }

    /** Putusan BPK masih boleh dicatat: sebelum naik, atau sesudah naik selama masih BT. */
    public function bolehCatatSiptl(SumberLaporan $jenis): bool
    {
        return $this->perluUnggah($jenis) || $this->perluCek($jenis);
    }

    /* ---- Nilai yang diakui ---- */

    /** Snapshot impor mendukung pengakuan sebagian; penilaian baru menggantikannya. */
    public function nilaiDiakuiItjen(): int
    {
        if ($this->nilai_diakui_itjen !== null) return min((int) $this->nilai, max(0, $this->nilai_diakui_itjen));
        return $this->hasil === HasilTelaah::M ? (int) $this->nilai : 0;
    }

    /** Sisi BPK: hanya baris yang sudah diunggah dan dinyatakan SS. */
    public function nilaiDiakuiBpk(): int
    {
        if ($this->sudahSiptl() && $this->nilai_diakui_bpk !== null) return min((int) $this->nilai, max(0, $this->nilai_diakui_bpk));
        return $this->statusSiptl() === StatusTindakLanjut::SS ? (int) $this->nilai : 0;
    }

    public function sisaNilaiItjen(): int { return max(0, (int) $this->nilai - $this->nilaiDiakuiItjen()); }
    public function sisaNilaiBpk(): int   { return max(0, (int) $this->nilai - $this->nilaiDiakuiBpk()); }

    /* ---- Bahasa tampilan ---- */

    /**
     * Siapa yang memegang dan apa yang sedang terjadi — hanya bahasa; perpindahan dan keputusan
     * milik pengendalinya.
     */
    public function presentasi(SumberLaporan $jenis): array
    {
        $alur = [
            'satker'         => ['Satuan kerja', 'Menyiapkan tanggapan'],
            'setba_tinjau'   => ['Setba', 'Meninjau kelengkapan tanggapan'],
            'uki'            => ['UKI', 'Menunggu telaah UKI'],
            'setba_teruskan' => ['Setba', 'Meneruskan hasil validasi UKI'],
            'inspektorat'    => ['Inspektorat', 'Menunggu hasil verifikasi'],
            'setba_kembali'  => ['Setba', 'Menyiapkan pengembalian berkas'],
        ];
        $pos = $this->pos();
        $isi = $alur[$pos->value] ?? null;
        $selesai = false;

        if ($pos === PosisiBerkas::SATKER && $this->kembali_dari) {
            $isi = ['Satuan kerja', 'Perlu memperbaiki tanggapan'];
        }
        if ($pos === PosisiBerkas::TUNTAS) {
            if (! $jenis->melewatiSiptl()) {
                $isi = ['Selesai', 'Verifikasi Inspektorat selesai'];
                $selesai = true;
            } elseif (! $this->sudahSiptl()) {
                $isi = ['Setba', 'Siap dicatat ke SIPTL'];
            } elseif ($this->status_bpk === StatusTindakLanjut::SS) {
                $isi = ['Selesai', 'Sudah sesuai menurut BPK'];
                $selesai = true;
            } elseif ($this->status_bpk === StatusTindakLanjut::BS) {
                $isi = ['Setba', 'Menindaklanjuti hasil BPK'];
            } else {
                $isi = ['BPK / SIPTL', 'Menunggu penilaian BPK'];
            }
        }

        [$pemegang, $judul] = $isi ?? ['Belum diketahui', 'Posisi berkas belum tercatat'];

        return compact('pemegang', 'judul', 'selesai');
    }

    /* ---- Filter ---- */

    public function scopeMilik($q, int|array|null $satkerId)
    {
        return $satkerId ? $q->whereIn('satker_id', (array) $satkerId) : $q;
    }

    /* ---- Penjaga aksi bersamaan ---- */

    private const KOLOM_KEADAAN = ['posisi', 'hasil', 'hasil_uki', 'status_bpk', 'siptl_tanggal', 'siptl_historis', 'nilai_diakui_itjen', 'nilai_diakui_bpk', 'updated_at'];

    /**
     * Dipanggil paling awal di transaksi tiap aksi: baris dikunci, lalu dibandingkan dengan yang
     * dibaca di awal permintaan. Berubah = sudah diproses orang lain (klik ganda, dua petugas).
     */
    public function kunciSejak(): void
    {
        $segar = static::query()->whereKey($this->getKey())->lockForUpdate()->first(self::KOLOM_KEADAAN);
        $tanda = fn (self $x) => array_map(fn ($k) => (string) $x->getRawOriginal($k), self::KOLOM_KEADAAN);
        if (! $segar || $tanda($segar) !== $tanda($this)) {
            throw new BerkasBerubah;
        }
    }
}
