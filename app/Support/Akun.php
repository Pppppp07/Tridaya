<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Urusan akun yang dipakai beberapa pintu sekaligus (27 Sep): formulir kata
 * password, SSO, Data master unit kerja, dan daftar Pengguna.
 */
class Akun
{
    /** Tandai berhasil masuk: waktu terakhir masuk, dan satu baris log. */
    public static function tandaiMasuk(User $u, string $cara): void
    {
        $u->forceFill(['terakhir_masuk_pada' => now()])->saveQuietly();
        Aktivitas::catat('masuk', $cara === 'sso' ? 'Masuk lewat SSO' : 'Masuk dengan password', [
            'oleh' => $u, 'rincian' => ['cara' => $cara],
        ]);
    }

    /**
     * Putuskan akun dari semua perangkatnya: sesi yang sedang berjalan dibuang
     * dan tanda "ingat saya" lama tidak berlaku lagi. Dipanggil saat akun
     * dinonaktifkan atau perannya diubah — orangnya tidak boleh terus bekerja
     * dengan hak lamanya sampai sesinya habis sendiri.
     *
     * Penjaga `PastikanAkunAktif` tetap ada untuk sesi yang tidak tersimpan di
     * basis data (penyimpan sesi selain `database`).
     */
    public static function putuskanSesi(User $u): void
    {
        $u->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $u->id)->delete();
        }
    }

    /**
     * Hubungkan akun lama yang belum punya NIP ke pegawainya di direktori,
     * menurut email-nya (27 Sep). Dipakai seeder demo dan perintah
     * `php artisan simtlhp:hubungkan-pegawai` — akun yang dibuat sebelum SSO
     * disiapkan dulu dengan cara ini, baru SSO dinyalakan.
     *
     * Yang diambil dari direktori: NIP, nama, jabatan. Peran, unit, dan kata
     * password-nya tidak disentuh. NIP yang sudah dipegang akun lain dilewati.
     *
     * @param  iterable<User>  $akun
     * @return int  berapa akun yang terhubung
     */
    public static function hubungkanKeDirektori(iterable $akun): int
    {
        $menurutEmail = DirektoriIrm::semua()->keyBy(fn ($p) => mb_strtolower((string) $p['email']));
        $n = 0;
        foreach ($akun as $u) {
            $p = $menurutEmail[mb_strtolower((string) $u->email)] ?? null;
            if ($u->nip || ! $p || User::where('nip', $p['nip'])->exists()) {
                continue;
            }
            $u->forceFill(['nip' => $p['nip'], 'name' => $p['nama'], 'jabatan' => $p['jabatan']])->save();
            $n++;
        }

        return $n;
    }

    /** NIP 18 angka polos — yang berspasi seperti di kartu pegawai dirapikan. */
    public static function rapikanNip(?string $nip): ?string
    {
        $n = preg_replace('/\D/', '', (string) $nip);

        return $n !== '' ? $n : null;
    }
}
