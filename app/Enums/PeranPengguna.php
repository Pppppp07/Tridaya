<?php

namespace App\Enums;

/* Hanya Setba yang boleh mengubah status, dengan menyalin surat verifikasi bernomor. */
enum PeranPengguna: string
{
    case SETBA = 'setba';
    case SATKER = 'satker';
    case UKI = 'uki';
    case INSPEKTORAT = 'inspektorat';
    case PIMPINAN = 'pimpinan';
    /* DTI: hanya melihat (log aktivitas dan rekomendasi). Pemegang Data master adalah ADMIN
       (Admin DTI). */
    case DTI = 'dti';
    case ADMIN = 'admin';

    public function nama(): string
    {
        return match ($this) {
            self::SETBA => 'Sekretariat Badan',
            self::SATKER => 'Satuan kerja',
            self::UKI => 'Unit Kepatuhan Internal',
            self::INSPEKTORAT => 'Inspektorat',
            self::PIMPINAN => 'Pimpinan',
            self::DTI => 'Data dan Teknologi Informasi',
            self::ADMIN => 'Administrator DTI',
        };
    }

    /** Sebutan pendek untuk kolom sempit dan keping. */
    public function pendek(): string
    {
        return match ($this) {
            self::SETBA => 'Setba',
            self::SATKER => 'Satuan kerja',
            self::UKI => 'UKI',
            self::INSPEKTORAT => 'Inspektorat',
            self::PIMPINAN => 'Pimpinan',
            self::DTI => 'DTI',
            self::ADMIN => 'Admin DTI',
        };
    }

    public function bolehUbahStatus(): bool
    {
        return $this === self::SETBA;
    }

    /** Peran yang hanya melihat; dijaga middleware HanyaMelihat. */
    public function hanyaMelihat(): bool
    {
        return in_array($this, [self::DTI, self::PIMPINAN], true);
    }

    /** Cakupan data satu unit; hak mengubah tetap mengikuti peran aktif. */
    public function terbatasSatker(): bool
    {
        return in_array($this, [self::SATKER, self::PIMPINAN], true);
    }

    /** Boleh membuka dan mengubah Data master: hanya Admin DTI. */
    public function kelolaMaster(): bool
    {
        return $this === self::ADMIN;
    }

    /** Boleh membaca Log aktivitas seluruh sistem. */
    public function bacaLog(): bool
    {
        return in_array($this, [self::DTI, self::ADMIN], true);
    }

    /**
     * Peran yang boleh diberikan peran ini lewat Data master. Akun satuan kerja lahir dari
     * penanggung jawab unit kerja.
     */
    public function bolehMemberi(): array
    {
        return match ($this) {
            self::ADMIN => [self::SETBA, self::UKI, self::INSPEKTORAT, self::PIMPINAN, self::DTI, self::ADMIN],
            default => [],
        };
    }

    /** Halaman yang terbuka bagi peran ini (sebutan saja; penjaganya menu dan tiap layar). */
    public function halaman(): array
    {
        return match ($this) {
            self::SETBA => ['Rekomendasi', 'Daftar laporan', 'Dashboard', 'Catat laporan baru'],
            self::SATKER => ['Rekomendasi', 'Daftar laporan'],
            self::UKI => ['Rekomendasi', 'Daftar laporan'],
            self::INSPEKTORAT => ['Rekomendasi', 'Daftar laporan'],
            self::PIMPINAN => ['Rekomendasi', 'Daftar laporan', 'Dashboard'],
            self::DTI => ['Rekomendasi', 'Daftar laporan', 'Log aktivitas'],
            self::ADMIN => ['Rekomendasi', 'Daftar laporan', 'Dashboard', 'Data Master', 'Log aktivitas'],
        };
    }

    /** Tugas peran dalam satu kalimat. */
    public function tugas(): string
    {
        return match ($this) {
            self::SETBA => 'Mencatat laporan baru, meneruskan berkas ke UKI dan Inspektorat, mengirim ulang berkas yang ditolak, dan mengurus SIPTL.',
            self::SATKER => 'Mengisi tanggapan tindak lanjut, melampirkan link bukti, dan mengirim berkas ke Setba untuk Satker yang ditugaskan kepada Anda.',
            self::UKI => 'Menelaah berkas yang diteruskan Setba, lalu mencatat hasil validasinya beserta suratnya.',
            self::INSPEKTORAT => 'Memverifikasi berkas yang diteruskan Setba, lalu mencatat hasilnya beserta surat CHV.',
            self::PIMPINAN => 'Memantau Dashboard, laporan, dan proses tindak lanjut Satker yang dipimpin. Hanya melihat.',
            self::DTI => 'Memantau log aktivitas dan alert keamanan, serta membaca laporan dan rekomendasi. Hanya melihat.',
            self::ADMIN => 'Mengelola user dan akses, satuan kerja, serta kategori dan referensi di Data Master; memantau log aktivitas dan seluruh rekomendasi.',
        };
    }

    /**
     * Yang bisa dilakukan peran ini, kalimat sehari-hari. Hanya yang bisa; penjaganya tetap di
     * pengendali dan middleware.
     */
    public function hak(): array
    {
        return match ($this) {
            self::SETBA => [
                'Melihat semua laporan dan rekomendasi',
                'Mencatat laporan baru',
                'Meneruskan berkas ke UKI dan Inspektorat, dan mengirim ulang yang ditolak',
                'Mencatat unggahan dan putusan SIPTL',
            ],
            self::SATKER => [
                'Melihat rekomendasi yang ditujukan ke unit Anda',
                'Melampirkan link bukti, menulis tanggapan, dan mengirimnya ke Setba',
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
                'Memantau Dashboard Satker yang dipimpin',
                'Membaca laporan, rekomendasi, bukti, dan riwayat tindak lanjut Satker yang dipimpin',
            ],
            self::DTI => [
                'Membuka Log aktivitas dan keaktifan akun',
                'Membaca semua laporan dan rekomendasi',
            ],
            self::ADMIN => [
                'Mengatur user dan akses, termasuk akun DTI dan Admin DTI',
                'Mengatur satuan kerja beserta penanggung jawabnya',
                'Mengatur kategori, sifat rekomendasi, bentuk tindak lanjut, dan sumber link bukti',
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
