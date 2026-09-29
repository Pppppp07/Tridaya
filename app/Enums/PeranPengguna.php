<?php

namespace App\Enums;

/* Banyak pihak memakai sistem, tapi hanya Setba yang boleh mengubah status —
   itu pun hanya dengan menyalin isi surat verifikasi bernomor. */
enum PeranPengguna: string
{
    case SETBA       = 'setba';
    case SATKER      = 'satker';
    case UKI         = 'uki';
    case INSPEKTORAT = 'inspektorat';
    case PIMPINAN    = 'pimpinan';
    /* Penjaga data (27 Sep). Bang Kamal: "ada satu akun hanya untuk DTI
       (Datik). DTI itu hanya untuk melihat jika dia merubah data, merusak
       data, kita tinggal nembak siapa pelakunya … Log aktivitas, sebatas itu.
       Terus data master enggak … kalau untuk rekomendasi, dikasih privilege
       tapi hanya nge-view doang." */
    case DTI         = 'dti';
    case ADMIN       = 'admin';

    public function nama(): string
    {
        return match ($this) {
            self::SETBA       => 'Sekretariat Badan',
            self::SATKER      => 'Satuan kerja',
            self::UKI         => 'Unit Kepatuhan Internal',
            self::INSPEKTORAT => 'Inspektorat',
            self::PIMPINAN    => 'Pimpinan',
            self::DTI         => 'Data dan Teknologi Informasi',
            self::ADMIN       => 'Administrator',
        };
    }

    /** Sebutan pendek untuk kolom sempit dan keping - "Setba", bukan
        "Sekretariat Badan". Nama panjangnya dipakai di kepala halaman. */
    public function pendek(): string
    {
        return match ($this) {
            self::SETBA       => 'Setba',
            self::SATKER      => 'Satuan kerja',
            self::UKI         => 'UKI',
            self::INSPEKTORAT => 'Inspektorat',
            self::PIMPINAN    => 'Pimpinan',
            self::DTI         => 'DTI',
            self::ADMIN       => 'Admin',
        };
    }

    public function bolehUbahStatus(): bool
    {
        return $this === self::SETBA;
    }

    /**
     * Peran yang hanya melihat (27 Sep): tidak ada satu pun isian yang boleh
     * ia kirim, selain keluar dan menandai pemberitahuannya sendiri. Dijaga
     * middleware `HanyaMelihat`, bukan cuma dengan menyembunyikan tombol.
     */
    public function hanyaMelihat(): bool
    {
        return in_array($this, [self::DTI, self::PIMPINAN], true);
    }

    /** Boleh membuka dan mengubah Data master. DTI sengaja tidak. */
    public function kelolaMaster(): bool
    {
        return in_array($this, [self::SETBA, self::ADMIN], true);
    }

    /** Boleh membaca Log aktivitas seluruh sistem. */
    public function bacaLog(): bool
    {
        return in_array($this, [self::DTI, self::ADMIN], true);
    }

    /**
     * Peran yang akunnya boleh diberikan lewat Data master oleh peran ini.
     * Setba mengatur petugas pusat; DTI dan Admin hanya diatur Admin, supaya
     * yang diawasi tidak bisa mengatur pengawasnya sendiri. Akun satuan kerja
     * tidak diberikan di sini — ia lahir dari penanggung jawab unit kerja.
     *
     * @return list<self>
     */
    public function bolehMemberi(): array
    {
        return match ($this) {
            self::ADMIN => [self::SETBA, self::UKI, self::INSPEKTORAT, self::PIMPINAN, self::DTI, self::ADMIN],
            self::SETBA => [self::SETBA, self::UKI, self::INSPEKTORAT, self::PIMPINAN],
            default     => [],
        };
    }

    /** Tugas peran ini dalam satu kalimat — Panduan singkat dan Profil (`TUGAS_PERAN` prototipe). */
    public function tugas(): string
    {
        return match ($this) {
            self::SETBA       => 'Mencatat laporan baru, meneruskan berkas ke UKI dan Inspektorat, mengirim ulang berkas yang ditolak, dan mengurus SIPTL.',
            self::SATKER      => 'Mengisi tindak lanjut unit Anda: unggah buktinya, tulis uraiannya, lalu kirim ke Setba. Anda hanya melihat bagian unit Anda sendiri.',
            self::UKI         => 'Menelaah berkas yang diteruskan Setba, lalu mencatat hasil validasinya beserta suratnya.',
            self::INSPEKTORAT => 'Memverifikasi berkas yang diteruskan Setba, lalu mencatat hasilnya beserta surat CHV.',
            self::PIMPINAN    => 'Memantau dashboard dan membaca rincian rekomendasi.',
            self::DTI         => 'Menjaga data: memantau Log aktivitas — siapa mengubah apa dan kapan — dan membaca rekomendasi. Akun ini hanya untuk melihat.',
            self::ADMIN       => 'Mengatur data master dan memantau seluruh rekomendasi.',
        };
    }

    /**
     * Yang bisa dilakukan peran ini, dengan kalimat sehari-hari — Profil →
     * Peran dan hak akses (28 Sep). Padanan `HAK_PERAN` prototipe. Hanya yang
     * bisa: yang tidak bisa tidak disebut (Hizkia: "hilangkan saja informasi
     * yang tidak bisa diakses"). Penjaganya tetap di pengendali dan
     * middleware; ini hanya sebutannya.
     *
     * @return list<string>
     */
    public function hak(): array
    {
        return match ($this) {
            self::SETBA => [
                'Melihat semua laporan dan rekomendasi',
                'Mencatat laporan baru',
                'Meneruskan berkas ke UKI dan Inspektorat, dan mengirim ulang yang ditolak',
                'Mencatat unggahan dan putusan SIPTL',
                'Mengatur Data master dan akun petugas pusat',
            ],
            self::SATKER => [
                'Melihat rekomendasi yang ditujukan ke unit Anda',
                'Mengunggah bukti, menulis uraian, dan mengirimnya ke Setba',
            ],
            self::UKI => [
                'Melihat semua laporan dan rekomendasi',
                'Mencatat hasil validasi beserta suratnya',
            ],
            self::INSPEKTORAT => [
                'Melihat semua laporan dan rekomendasi',
                'Mencatat hasil verifikasi beserta surat CHV',
            ],
            self::PIMPINAN => [
                'Membuka Dashboard',
                'Membaca semua laporan dan rekomendasi',
            ],
            self::DTI => [
                'Membuka Log aktivitas dan keaktifan akun',
                'Membaca semua laporan dan rekomendasi',
            ],
            self::ADMIN => [
                'Mengatur seluruh Data master, termasuk akun DTI dan Admin',
                'Membuka Log aktivitas dan Dashboard',
                'Melihat semua laporan dan rekomendasi',
            ],
        };
    }

    /** Peran pusat — yang diberikan lewat daftar Pengguna, bukan lewat unit kerja. */
    public static function pusat(): array
    {
        return [self::SETBA, self::UKI, self::INSPEKTORAT, self::PIMPINAN, self::DTI, self::ADMIN];
    }
}
