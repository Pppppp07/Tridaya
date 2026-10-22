<?php

namespace App\Support;

/**
 * Enam belas bentuk tindak lanjut SOP beserta dokumen yang lazim diminta — isi awal data master
 * (penyemai dan migrasi). Sesudahnya daftarnya diatur Admin DTI di Data Master.
 */
class BentukTindakLanjut
{
    /** @return array<string, list<string>> nama bentuk => usulan dokumen */
    public static function peta(): array
    {
        return [
            'Surat teguran' => [
                'Surat teguran bernomor',
                'Tanda terima oleh yang bersangkutan',
            ],
            'Bukti setor ke kas negara' => [
                'Bukti setor ke kas negara (SSBP)',
                'Nota Konfirmasi KPPN',
                'Rekapitulasi nilai yang disetor',
            ],
            'Surat instruksi' => [
                'Surat instruksi bernomor',
                'Bukti penyampaian kepada pelaksana',
            ],
            'Sosialisasi' => [
                'Undangan sosialisasi',
                'Daftar hadir',
                'Materi sosialisasi',
            ],
            'Sanksi administratif atau teguran tertulis' => [
                'Surat keputusan atau surat teguran bernomor',
                'Tanda terima oleh yang bersangkutan',
            ],
            'Pengamanan aset' => [
                'Berita acara pengamanan aset',
                'Foto kondisi aset',
                'Bukti pencatatan pada aplikasi BMN',
            ],
            'Bukti inventarisasi dan penatausahaan BMN' => [
                'Laporan hasil inventarisasi BMN',
                'Berita acara rekonsiliasi BMN',
            ],
            'Penyetoran ke kas negara' => [
                'Bukti setor ke kas negara (SSBP)',
                'Nota Konfirmasi KPPN',
                'Rekapitulasi nilai yang disetor',
            ],
            'Penyerahan barang atau aset kepada negara' => [
                'Berita acara serah terima barang',
                'Kartu inventaris yang sudah diperbarui',
                'Foto barang yang diserahkan',
            ],
            'Pelimpahan kepada aparat penegak hukum' => [
                'Surat pelimpahan kepada aparat penegak hukum',
                'Tanda terima dari instansi penegak hukum',
                'Laporan perkembangan penanganan perkara',
            ],
            'Tindakan administratif atau hukuman disiplin' => [
                'Surat keputusan atau surat teguran bernomor',
                'Tanda terima oleh yang bersangkutan',
                'Bukti pencatatan pada berkas kepegawaian',
            ],
            'Perbaikan sistem pengendalian intern' => [
                'Prosedur atau pedoman hasil revisi',
                'Surat penetapan oleh pejabat berwenang',
                'Bukti sosialisasi kepada pelaksana',
            ],
            'Perbaikan hasil pekerjaan' => [
                'Laporan hasil pelaksanaan perbaikan',
                'Foto dokumentasi 0%, 50%, dan 100%',
                'Back up data kuantitas dan kualitas',
                'Surat pernyataan biaya perbaikan ditanggung penyedia jasa',
            ],
            'Pengenaan sanksi daftar hitam' => [
                'Surat penetapan sanksi daftar hitam',
                'Bukti penayangan pada Daftar Hitam Nasional',
                'Berita acara hasil evaluasi penyedia',
            ],
            'Perbaikan dokumen administrasi' => [
                'Dokumen yang sudah diperbaiki',
                'Surat pengantar penyampaian dokumen',
            ],
            'Lainnya sesuai LHP' => [
                'Dokumen pendukung sesuai bunyi rekomendasi',
            ],
        ];
    }

    /** Nama bentuknya saja, urut baku. */
    public static function nama(): array
    {
        return array_keys(self::peta());
    }

    /**
     * Sebutan lama → sebutan SOP, untuk ganti nama di tempat (tindakan lama tetap menunjuk
     * barisnya).
     */
    public static function petaLama(): array
    {
        return [
            'Penyetoran ke kas negara'        => 'Penyetoran ke kas negara',
            'Perbaikan fisik pekerjaan'       => 'Perbaikan hasil pekerjaan',
            'Penyerahan aset'                 => 'Penyerahan barang atau aset kepada negara',
            'Perbaikan dokumen administrasi'  => 'Perbaikan dokumen administrasi',
            'Perbaikan prosedur atau pedoman' => 'Perbaikan sistem pengendalian intern',
            'Sanksi kepegawaian'              => 'Tindakan administratif atau hukuman disiplin',
            'Pengembangan kompetensi pejabat' => null,
            'Lainnya'                         => 'Lainnya sesuai LHP',
        ];
    }
}
