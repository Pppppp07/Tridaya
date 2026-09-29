<?php

namespace App\Support\Sso;

/**
 * Siapa orangnya menurut SSO — satu paket atribut dari eHRM (27 Sep).
 *
 * Bang Kamal: "kalau ngambil atribut ya nama, NIP, gitu kan. Ini jangan
 * tertukar atributnya." Karena itu atributnya selalu dibaca bersama dari satu
 * jawaban penyedia SSO, tidak pernah dirakit dari dua sumber.
 */
final class Identitas
{
    public function __construct(
        public readonly string $nip,
        public readonly string $nama = '',
        public readonly string $email = '',
        public readonly string $jabatan = '',
        public readonly string $unit = '',
        /** Klaim aslinya, untuk log bila ada yang perlu diperiksa. */
        public readonly array $mentah = [],
    ) {}

    /**
     * Dari klaim penyedia SSO menurut peta nama klaim (config
     * simtlhp.sso.oidc.klaim). Nama klaim boleh bertitik untuk yang bersarang.
     */
    public static function dariKlaim(array $klaim, array $peta): self
    {
        $ambil = fn (string $k) => trim((string) data_get($klaim, (string) ($peta[$k] ?? $k), ''));

        return new self(
            nip: (string) preg_replace('/\D/', '', $ambil('nip')),
            nama: $ambil('nama'),
            email: mb_strtolower($ambil('email')),
            jabatan: $ambil('jabatan'),
            unit: $ambil('unit'),
            mentah: $klaim,
        );
    }

    /** Dari satu baris direktori pegawai (DirektoriIrm). */
    public static function dariPegawai(array $p): self
    {
        return new self(
            nip: (string) preg_replace('/\D/', '', (string) ($p['nip'] ?? '')),
            nama: (string) ($p['nama'] ?? ''),
            email: mb_strtolower((string) ($p['email'] ?? '')),
            jabatan: (string) ($p['jabatan'] ?? ''),
            unit: (string) ($p['unitNama'] ?? $p['unit'] ?? ''),
            mentah: $p,
        );
    }
}
