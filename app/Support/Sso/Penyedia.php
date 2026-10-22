<?php

namespace App\Support\Sso;

/**
 * Pilih penyedia SSO menurut `SIMTLHP_SSO`; penyedia lain cukup memenuhi PenyediaSso dan
 * didaftarkan di KELAS.
 */
class Penyedia
{
    private const KELAS = [
        'oidc' => SsoOidc::class,
        'dwaradaya' => SsoDwaradaya::class,
    ];

    /** Nama penyedia yang berlaku; yang tidak dikenal berarti mati. */
    public static function nama(): string
    {
        $n = (string) config('simtlhp.sso.driver', 'mati');

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
