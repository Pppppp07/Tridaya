<?php

namespace App\Support\Sso;

use App\Models\User;
use App\Support\Aktivitas;
use App\Support\Akun;

/**
 * Boleh tidaknya orang yang dibuktikan SSO masuk: hanya yang terdaftar di Data master (petugas
 * pusat dan penanggung jawab unit). Kuncinya NIP.
 */
class Otorisasi
{
    /** Identitas JWT Dwaradaya terverifikasi; email hanya dipakai bila tidak ada NIP. */
    public static function dwaradaya(Identitas $id): array
    {
        if ($id->nip !== '') {
            return self::periksa($id);
        }
        $users = User::with('satker')->whereRaw('LOWER(email) = ?', [$id->email])->limit(2)->get();
        if ($id->email === '' || $users->count() !== 1) {
            return [null, 'Akun Anda belum memiliki izin masuk. Hubungi Admin DTI.'];
        }
        $u = $users->first();
        if (! $u->aktif) {
            return [null, 'Izin masuk akun Anda sudah dinonaktifkan. Hubungi Admin DTI.'];
        }

        return [$u, null];
    }

    public static function periksa(Identitas $id): array
    {
        $nip = Akun::rapikanNip($id->nip);
        if (! $nip) {
            return [null, 'Identitas dari SSO tidak membawa NIP.'];
        }

        $u = User::with('satker')->where('nip', $nip)->first();
        if (! $u) {
            return [null, 'NIP Anda belum terdaftar sebagai pengguna sistem ini.'];
        }
        if (! $u->aktif) {
            return [null, 'Izin masuk akun Anda di sistem ini sudah dinonaktifkan. Hubungi Admin DTI untuk informasi atau pengaktifan kembali.'];
        }

        return [$u, null];
    }

    /**
     * Samakan nama, jabatan, email dengan eHRM. Email yang sudah dipakai akun lain tidak
     * ditimpakan.
     */
    public static function samakan(User $u, Identitas $id): array
    {
        if (! config('simtlhp.sso.sinkron_atribut', true)) {
            return [];
        }
        $baru = array_filter([
            'name' => $id->nama,
            'jabatan' => $id->jabatan,
            'email' => $id->email,
        ], fn ($v) => $v !== '');
        if (isset($baru['email']) && User::where('email', $baru['email'])->whereKeyNot($u->id)->exists()) {
            unset($baru['email']);
        }
        $lama = array_intersect_key($u->only(['name', 'jabatan', 'email']), $baru);
        $beda = Aktivitas::beda($lama, $baru);
        if ($beda) {
            $u->forceFill($beda['sesudah'])->saveQuietly();
        }

        return $beda;
    }
}
