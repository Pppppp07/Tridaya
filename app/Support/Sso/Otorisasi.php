<?php

namespace App\Support\Sso;

use App\Enums\PeranPengguna;
use App\Models\User;
use App\Support\Akun;

/**
 * Boleh tidaknya orang yang dibuktikan SSO masuk, dan sebagai apa (27 Sep).
 *
 * SSO eHRM membuktikan "ini pegawai Kementerian bernama X, NIP Y". Seluruh
 * pegawai punya akun eHRM, tapi yang boleh masuk ke aplikasi ini hanya yang
 * didaftarkan Setba di Data master:
 *
 * - petugas pusat (Setba, UKI, Inspektorat, Pimpinan, DTI, Admin) — daftar
 *   Pengguna;
 * - satu penanggung jawab tiap unit kerja — Bang Kamal: "Cukup satu orang aja
 *   yang nginput".
 *
 * Kuncinya NIP, bukan email atau nama: NIP yang tidak pernah berubah dan
 * tidak kembar (dijaga indeks unik basis data).
 */
class Otorisasi
{
    /**
     * @return array{0: ?User, 1: ?string}  akun yang boleh masuk, atau alasan penolakannya
     */
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
            return [null, $u->peran === PeranPengguna::SATKER
                ? 'Anda bukan lagi penanggung jawab '.($u->satker?->namaPendek() ?? 'unit kerja').' di sistem ini.'
                : 'Akun Anda di sistem ini sudah dinonaktifkan.'];
        }

        return [$u, null];
    }

    /**
     * Samakan nama, jabatan, dan email akun dengan eHRM (config
     * simtlhp.sso.sinkron_atribut). Email yang ternyata sudah dipakai akun
     * lain tidak ditimpakan — dua akun ber-email sama membuat pemberitahuan email
     * nyasar.
     *
     * @return array{sebelum?:array, sesudah?:array} yang berubah, untuk log
     */
    public static function samakan(User $u, Identitas $id): array
    {
        if (! config('simtlhp.sso.sinkron_atribut', true)) {
            return [];
        }
        $baru = array_filter([
            'name'    => $id->nama,
            'jabatan' => $id->jabatan,
            'email'   => $id->email,
        ], fn ($v) => $v !== '');
        if (isset($baru['email']) && User::where('email', $baru['email'])->whereKeyNot($u->id)->exists()) {
            unset($baru['email']);
        }
        $lama = array_intersect_key($u->only(['name', 'jabatan', 'email']), $baru);
        $beda = \App\Support\Aktivitas::beda($lama, $baru);
        if ($beda) {
            $u->forceFill($beda['sesudah'])->saveQuietly();
        }

        return $beda;
    }
}
