<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Urusan akun yang dipakai beberapa pintu (password, SSO, Data master, daftar Pengguna). */
class Akun
{
    /** Akun baru memakai password acak; password demo selalu diabaikan di production. */
    public static function passwordAwal(): string
    {
        $demo = app()->isProduction() ? null : config('simtlhp.password_demo');

        return (string) ($demo ?: Str::random(40));
    }

    /** Tandai berhasil masuk: waktu terakhir masuk, dan satu baris log. */
    public static function tandaiMasuk(User $u, string $cara): void
    {
        if (request()->hasSession()) {
            /* Halaman terakhir milik akun lain (sesinya habis di peramban ini) tidak dibawa. */
            $oleh = request()->session()->pull('url.intended_oleh');
            if ($oleh !== null && (int) $oleh !== (int) $u->id) {
                request()->session()->forget('url.intended');
            }
            request()->session()->forget(['akses_aktif', 'sesi_berakhir']);
            request()->session()->put('izin_akun', ['user' => $u->id, 'versi' => $u->versi_akses ?? 1]);
        }
        $u->forceFill(['terakhir_masuk_pada' => Aktivitas::waktuKini()])->saveQuietly();
        Aktivitas::catat('masuk', $cara === 'sso' ? 'Masuk lewat SSO' : 'Masuk dengan password', [
            'oleh' => $u, 'rincian' => ['cara' => $cara],
        ]);
    }

    /**
     * Putuskan akun dari semua perangkatnya (sesi dibuang, "ingat saya" lama batal) saat
     * dinonaktifkan atau perannya diubah.
     */
    public static function putuskanSesi(User $u): void
    {
        // Dua pencabutan yang bersamaan tidak boleh menulis versi lama yang sama.
        User::whereKey($u->id)->increment('versi_akses', 1, ['remember_token' => Str::random(60)]);
        $u->refresh();
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $u->id)->delete();
        }
    }

    /**
     * Hubungkan akun lama tanpa NIP ke pegawainya menurut email (seeder demo dan
     * `simtlhp:hubungkan-pegawai`). Yang diambil: NIP, nama, jabatan.
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
