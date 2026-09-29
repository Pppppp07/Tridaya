<?php

namespace App\Models;

use App\Enums\HasilTelaah;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Enums\SumberLaporan;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu satuan kerja pada satu bentuk tindak lanjut — satu baris penugasan.
 *
 * Inilah yang menempuh proses. Rekomendasi tidak: posisi berkas, tiga
 * penilaian (UKI, Inspektorat, BPK), unggahan SIPTL, dan pemberkasan ulang
 * semuanya milik baris ini. Satu rekomendasi yang dipikul tiga satuan kerja
 * berisi tiga perjalanan yang berbeda, dan bisa berada di tiga meja sekaligus.
 */
class Sasaran extends Model
{
    /* `siptl_tanggal` sengaja tidak di sini. Tanggal unggah SIPTL dikunci
       sekali tercatat, dan kolom yang bisa diisi massal bisa ditimpa lewat
       `update($request->all())` tanpa sengaja. Penulisnya satu:
       `CatatSiptl`, lewat forceFill. */
    protected $fillable = ['tindakan_id', 'satker_id', 'nilai',
        'nilai_ss', 'nilai_memadai', 'posisi', 'hasil', 'hasil_uki', 'catatan',
        'status_bpk', 'catatan_bpk', 'tgl_pantau',
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
    ];

    public function tindakan() { return $this->belongsTo(Tindakan::class); }
    public function satker()   { return $this->belongsTo(Satker::class); }

    public function tanggapan()         { return $this->hasMany(Tanggapan::class)->orderBy('tanggal')->orderBy('id'); }
    public function pemulihan()         { return $this->hasMany(Pemulihan::class)->orderBy('tanggal')->orderBy('id'); }
    public function permintaanDokumen() { return $this->hasMany(PermintaanDokumen::class)->orderBy('tanggal')->orderBy('id'); }
    public function pengembalian()      { return $this->hasMany(Pengembalian::class)->orderBy('tanggal')->orderBy('id'); }
    public function telaah()            { return $this->hasMany(Telaah::class)->orderBy('tanggal')->orderBy('id'); }
    public function riwayat()           { return $this->hasMany(RiwayatBerkas::class)->orderBy('waktu'); }
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

    /* ================================================================
       POSISI
       ================================================================ */

    /**
     * Posisi baris ini, dan satu-satunya cara membacanya. Baris yang belum
     * pernah bergerak masih di satuan kerja — tidak meminjam posisi siapa pun.
     */
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

    /* ================================================================
       URUSAN SIPTL — hanya jalur yang lewat BPK
       ================================================================ */

    /**
     * Satu baris naik ke SIPTL begitu tindak lanjutnya selesai diperiksa —
     * tidak menunggu satuan kerja lain.
     */
    public function perluUnggah(SumberLaporan $jenis): bool
    {
        return $jenis->melewatiSiptl() && $this->tuntas() && ! $this->siptl_tanggal;
    }

    /**
     * Sudah diunggah, dan BPK belum memutus apa pun atas unggahan ITU.
     *
     * Putusan BPK dicatat sekali tiap unggahan. Kata Hizkia: "perubahan status
     * SS dan BS itu hanya boleh dilakukan sekali setelah proses Upload SIPTL."
     * Jadi baris ber-BS tidak lagi menunggu penilaian — penilaiannya sudah
     * turun, dan yang berikutnya mengirimnya kembali ke satuan kerja.
     */
    public function perluCek(SumberLaporan $jenis): bool
    {
        return $jenis->melewatiSiptl() && $this->siptl_tanggal
            && ($this->status_bpk ?? StatusTindakLanjut::BT) === StatusTindakLanjut::BT;
    }

    /**
     * BPK memutus Belum Sesuai atas unggahan ini. Yang berikutnya bukan
     * mengecek SIPTL lagi: berkasnya dikirim kembali ke satuan kerja,
     * diperbaiki, lalu diunggah ulang — barulah BPK punya sesuatu yang baru
     * untuk dinilai.
     */
    public function perluKirimUlang(SumberLaporan $jenis): bool
    {
        return $jenis->melewatiSiptl() && $this->siptl_tanggal
            && $this->status_bpk === StatusTindakLanjut::BS;
    }

    /**
     * Putusan BPK masih boleh dicatat untuk baris ini: sebelum berkasnya naik
     * (yang dicatat tanggalnya) atau sesudah naik selama statusnya masih BT.
     */
    public function bolehCatatSiptl(SumberLaporan $jenis): bool
    {
        return $this->perluUnggah($jenis) || $this->perluCek($jenis);
    }

    /* ================================================================
       NILAI YANG DIAKUI
       ================================================================ */

    /**
     * Medan kosong berarti "belum ada angkanya sendiri", bukan nol — yang
     * membacanya jatuh ke keadaannya: utuh bila sudah beres, nol bila belum.
     */
    public function nilaiDiakuiItjen(): int
    {
        return $this->nilai_memadai !== null
            ? (int) $this->nilai_memadai
            : ($this->hasil === HasilTelaah::M ? (int) $this->nilai : 0);
    }

    /**
     * Sisi BPK dibaca dari status baris ini sendiri. Baris yang belum pernah
     * berstatus jatuh ke status rekomendasinya — catatan lama.
     */
    public function nilaiDiakuiBpk(?Rekomendasi $rek = null): int
    {
        if ($this->nilai_ss !== null) {
            return (int) $this->nilai_ss;
        }
        if ($this->status_bpk) {
            return $this->status_bpk === StatusTindakLanjut::SS ? (int) $this->nilai : 0;
        }
        $rek ??= $this->rekomendasi();

        return $rek && $rek->status === StatusTindakLanjut::SS ? (int) $this->nilai : 0;
    }

    public function sisaNilaiItjen(): int { return max(0, (int) $this->nilai - $this->nilaiDiakuiItjen()); }
    public function sisaNilaiBpk(): int   { return max(0, (int) $this->nilai - $this->nilaiDiakuiBpk()); }

    /* ================================================================
       BAHASA TAMPILAN — padanan `presentasiBerkas` prototipe
       ================================================================ */

    /**
     * Siapa yang memegang dan apa yang sedang terjadi. Hanya bahasa:
     * perpindahan berkas dan keputusan tetap milik pengendalinya.
     *
     * Kalimat "langkah berikutnya" dibuang 21 Sep — kata Hizkia, "keterangan
     * langkah berikutnya dihilangkan saja". Tabel tindak lanjut kini membaca
     * catatan terbaru dari riwayatnya (RiwayatTindakLanjut::catatanTerakhir).
     *
     * @return array{pemegang:string, judul:string, selesai:bool}
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
            } elseif (! $this->siptl_tanggal) {
                $isi = ['Setba', 'Siap dicatat ke SIPTL'];
            } elseif (in_array($this->status_bpk, [StatusTindakLanjut::SS, StatusTindakLanjut::TD], true)) {
                $isi = ['Selesai', $this->status_bpk === StatusTindakLanjut::SS ? 'Sudah sesuai menurut BPK' : 'Tidak dapat ditindaklanjuti menurut BPK'];
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

    /* ================================================================
       FILTER
       ================================================================ */

    public function scopeMilik($q, ?int $satkerId)
    {
        return $satkerId ? $q->where('satker_id', $satkerId) : $q;
    }
}
