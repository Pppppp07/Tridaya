<?php

namespace App\Support\Sso;

/**
 * Memilih penyedia SSO menurut `SIMTLHP_SSO` (27 Sep).
 *
 * Penyedia lain — mis. API eHRM khusus Pusdatin, atau SAML — cukup ditulis
 * sebagai kelas yang memenuhi PenyediaSso lalu didaftarkan di KELAS di bawah
 * (atau config simtlhp.sso.kelas). Tidak ada bagian lain yang perlu diubah.
 */
class Penyedia
{
    private const KELAS = [
        'simulasi' => SsoSimulasi::class,
        'oidc'     => SsoOidc::class,
    ];

    /** Nama penyedia yang berlaku. Simulasi tidak pernah berlaku di produksi. */
    public static function nama(): string
    {
        $n = (string) config('simtlhp.sso.driver', 'mati');
        if ($n === 'simulasi' && app()->isProduction()) {
            return 'mati';
        }

        return array_key_exists($n, self::kelas()) ? $n : 'mati';
    }

    public static function aktif(): bool
    {
        return self::nama() !== 'mati';
    }

    public static function driver(): PenyediaSso
    {
        abort_unless(self::aktif(), 404);

        return app(self::kelas()[self::nama()]);
    }

    public static function alamatKeluar(): ?string
    {
        return self::aktif() ? self::driver()->alamatKeluar() : null;
    }

    /** @return array<string, class-string<PenyediaSso>> */
    private static function kelas(): array
    {
        return array_merge(self::KELAS, (array) config('simtlhp.sso.kelas', []));
    }
}
