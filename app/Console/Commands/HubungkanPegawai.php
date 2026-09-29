<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Aktivitas;
use App\Support\Akun;
use Illuminate\Console\Command;

/**
 * Menghubungkan akun yang belum punya NIP ke pegawainya di direktori menurut
 * email-nya (27 Sep).
 *
 * Masuk lewat SSO mengenali orang dari NIP. Akun yang dibuat sebelum SSO
 * disiapkan — akun password lama — perlu NIP dulu, kalau tidak pemiliknya
 * akan ditolak ("belum terdaftar") begitu SSO dinyalakan. Jalankan ini
 * sebelum SIMTLHP_MASUK_PASSWORD dipadamkan; akun yang tidak terhubung disebutkan
 * supaya bisa dirapikan satu per satu di tab Pengguna.
 *
 *   php artisan simtlhp:hubungkan-pegawai
 */
class HubungkanPegawai extends Command
{
    protected $signature = 'simtlhp:hubungkan-pegawai';

    protected $description = 'Hubungkan akun tanpa NIP ke pegawai direktori (eHRM) menurut email, sebelum SSO diaktifkan';

    public function handle(): int
    {
        $tanpa = User::whereNull('nip')->orderBy('id')->get();
        if ($tanpa->isEmpty()) {
            $this->info('Semua akun sudah punya NIP.');

            return self::SUCCESS;
        }

        $n = Akun::hubungkanKeDirektori($tanpa);
        $sisa = User::whereNull('nip')->orderBy('id')->get();
        if ($n) {
            Aktivitas::catat('master.pengguna.hubungkan', $n.' akun dihubungkan ke direktori pegawai menurut email', [
                'oleh' => null, 'nama' => 'Perintah server', 'kelompok' => 'pengguna',
            ]);
        }

        $this->info($n.' akun dihubungkan.');
        if ($sisa->isNotEmpty()) {
            $this->warn($sisa->count().' akun belum terhubung (email-nya tidak ada di direktori):');
            foreach ($sisa as $u) {
                $this->line('  - '.$u->name.' <'.$u->email.'> '.$u->peran->pendek().($u->aktif ? '' : ' (nonaktif)'));
            }
        }

        return self::SUCCESS;
    }
}
