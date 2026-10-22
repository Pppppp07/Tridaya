<?php

namespace App\Support;

use App\Enums\PeranPengguna;

/**
 * "Sebelum → sesudah" dari rincian log dalam kalimat pendek; dipakai tampilan dan unduhan CSV.
 * Hanya medan yang bernama yang ditampilkan.
 */
final class BedaLog
{
    /** Nama tiap medan yang dikenal; pengaturan akun mengikuti Setelan::NAMA. */
    public const NAMA = [
        'nama' => 'Nama', 'pendek' => 'Nama pendek', 'kode_satker' => 'Kode satker', 'aktif' => 'Status', 'peran' => 'Peran', 'perans' => 'Peran yang diberikan', 'peran_aktif' => 'Peran aktif',
        'uang' => 'Perlu penyetoran', 'pj' => 'Penanggung jawab', 'nip' => 'NIP', 'sumber' => 'Sumber',
        'dokumen' => 'Dokumen usulan',
        'satker_pimpinan' => 'Satker yang dipimpin',
        /* Menggabungkan, mengurutkan, dan mengakhiri sesi. */
        'kategori' => 'Kategori', 'dipindah' => 'Dipindahkan', 'urutan' => 'Urutan', 'sesi' => 'Sesi diakhiri',
        'host' => 'Domain', 'prefix' => 'Jalur berkas',
    ];

    /** @return array<string, string> */
    public static function nama(): array
    {
        return self::NAMA + Setelan::NAMA;
    }

    /** Nilai satu medan untuk dibaca orang. */
    public static function nilai(string $k, mixed $v): string
    {
        if ($v === null || $v === '') {
            return '—';
        }

        return match ($k) {
            'aktif' => $v ? 'aktif' : 'nonaktif',
            'uang' => $v ? 'ya' : 'tidak',
            'peran', 'peran_aktif' => PeranPengguna::tryFrom((string) $v)?->pendek() ?? (string) $v,
            'perans' => implode(', ', array_map(fn ($p) => PeranPengguna::tryFrom((string) $p)?->pendek() ?? (string) $p, (array) $v)) ?: '—',
            'popup', 'email', 'animasi', 'beranda' => Setelan::sebut($k, $v),
            default => (string) $v,
        };
    }

    /** Medan yang berubah, hanya yang bernama. */
    public static function butir(?array $rincian): array
    {
        $s = (array) ($rincian['sebelum'] ?? []);
        $d = (array) ($rincian['sesudah'] ?? []);
        $nama = self::nama();
        $hasil = [];
        foreach (array_unique(array_merge(array_keys($s), array_keys($d))) as $k) {
            if (! isset($nama[$k])) {
                continue;
            }
            $hasil[] = ['k' => $k, 'nama' => $nama[$k], 'ada_sebelum' => array_key_exists($k, $s),
                'sebelum' => self::nilai($k, $s[$k] ?? null), 'sesudah' => self::nilai($k, $d[$k] ?? null)];
        }

        return $hasil;
    }

    /** Kalimat datar untuk unduhan CSV: "Status: aktif → nonaktif; Peran: …". */
    public static function teks(?array $rincian): string
    {
        return implode('; ', array_map(
            fn ($b) => $b['nama'].': '.($b['ada_sebelum'] ? $b['sebelum'].' → ' : '').$b['sesudah'],
            self::butir($rincian)));
    }
}
