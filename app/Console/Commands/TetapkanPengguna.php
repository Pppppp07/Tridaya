<?php

namespace App\Console\Commands;

use App\Enums\PeranPengguna;
use App\Models\User;
use App\Support\Aktivitas;
use App\Support\Akun;
use App\Support\DirektoriIrm;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Menetapkan satu pengguna pusat dari direktori pegawai menurut NIP (27 Sep).
 *
 * Untuk server yang baru dipasang dengan SSO saja: belum ada satu pun akun
 * yang bisa membuka Data master, jadi Admin atau Setba pertama didaftarkan
 * dari sini, lalu sisanya lewat tab Pengguna.
 *
 *   php artisan simtlhp:pengguna 199102242018031006 admin
 *
 * Nama, jabatan, dan email diambil dari direktori (eHRM), tidak diketik.
 * Password-nya acak — orangnya masuk lewat SSO. Tercatat di log aktivitas.
 */
class TetapkanPengguna extends Command
{
    protected $signature = 'simtlhp:pengguna {nip : NIP 18 angka} {peran : setba|uki|inspektorat|pimpinan|dti|admin}';

    protected $description = 'Daftarkan (atau aktifkan kembali) satu pengguna pusat dari direktori pegawai menurut NIP';

    public function handle(): int
    {
        $peran = PeranPengguna::tryFrom((string) $this->argument('peran'));
        if (! $peran || ! in_array($peran, PeranPengguna::pusat(), true)) {
            $this->error('Peran harus salah satu: setba, uki, inspektorat, pimpinan, dti, admin. Penanggung jawab unit kerja ditetapkan lewat Data master.');

            return self::FAILURE;
        }
        $p = DirektoriIrm::nip((string) $this->argument('nip'));
        if (! $p) {
            $this->error(DirektoriIrm::error() ?: 'NIP itu tidak ada di direktori pegawai.');

            return self::FAILURE;
        }

        $akun = User::where('nip', $p['nip'])->first();
        if ($akun && $akun->peran === PeranPengguna::SATKER && $akun->aktif) {
            $this->error($p['nama'].' sedang menjadi penanggung jawab unit kerja. Ganti penanggung jawabnya dulu lewat Data master.');

            return self::FAILURE;
        }
        if (User::where('email', $p['email'])->when($akun, fn ($q) => $q->whereKeyNot($akun->id))->exists()) {
            $this->error('Email '.$p['email'].' sudah dipakai akun lain.');

            return self::FAILURE;
        }

        $sebelum = $akun ? ['peran' => $akun->peran->value, 'aktif' => $akun->aktif] : [];
        /* Password acak — orangnya masuk lewat SSO. Pada demo boleh
           dipatok SIMTLHP_PASSWORD_DEMO, sama dengan akun dari Data master. */
        $akun ??= new User(['password' => (string) (config('simtlhp.password_demo') ?: Str::random(40))]);
        $akun->fill([
            'name' => $p['nama'], 'nip' => $p['nip'], 'jabatan' => $p['jabatan'], 'email' => $p['email'],
            'peran' => $peran->value, 'satker_id' => null, 'aktif' => true,
        ])->save();
        if ($sebelum && $sebelum['peran'] !== $peran->value) {
            Akun::putuskanSesi($akun);
        }
        Aktivitas::catat('master.pengguna.tambah', 'Menetapkan '.$p['nama'].' sebagai '.$peran->pendek().' lewat perintah server', [
            'oleh' => null, 'nama' => 'Perintah server', 'kelompok' => 'pengguna', 'subjek' => $akun,
            'rincian' => ['sebelum' => $sebelum, 'sesudah' => ['peran' => $peran->value, 'aktif' => true, 'nip' => $p['nip']]],
        ]);

        $this->info($p['nama'].' ('.$p['email'].') kini '.$peran->pendek().'. Masuk lewat SSO dengan NIP '.$p['nip'].'.');

        return self::SUCCESS;
    }
}
