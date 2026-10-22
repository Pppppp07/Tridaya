<?php

namespace App\Support;

use App\Enums\PeranPengguna as P;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pengaturan akun sendiri di `users.pengaturan` (JSON): popup, email, pembaruan, animasi, tema, menu,
 * beranda.
 * Yang belum dipilih mengikuti bawaan peran.
 */
class Setelan
{
    public const BERANDA = [
        'rekomendasi' => ['nama' => 'Rekomendasi', 'rute' => 'rekomendasi.index'],
        'laporan' => ['nama' => 'Daftar laporan', 'rute' => 'laporan.index'],
        'dashboard' => ['nama' => 'Dashboard', 'rute' => 'ringkasan', 'peran' => [P::SETBA, P::PIMPINAN, P::ADMIN]],
        'log' => ['nama' => 'Log aktivitas', 'rute' => 'log', 'peran' => [P::DTI, P::ADMIN]],
        'master' => ['nama' => 'Data master', 'rute' => 'master', 'peran' => [P::ADMIN]],
        'pemberitahuan' => ['nama' => 'Pemberitahuan', 'rute' => 'pemberitahuan'],
    ];

    /** Nama tiap pengaturan di log, sebelum → sesudah (bagian/beda-log). */
    public const NAMA = ['popup' => 'Pop-up pemberitahuan', 'email' => 'Email pemberitahuan', 'pembaruan' => 'Sorot pembaruan', 'animasi' => 'Animasi', 'beranda' => 'Halaman pertama', 'tema' => 'Tema', 'menu' => 'Menu samping'];

    /** @return array{popup:bool, email:bool, pembaruan:bool, animasi:string, beranda:string, tema:string, menu:string} */
    public static function bawaan(User $u): array
    {
        return [
            'popup' => true,
            'email' => $u->peran === P::SATKER,
            /* Daftar Rekomendasi: yang baru diperbarui di atas, bertitik merah sampai dibuka. */
            'pembaruan' => true,
            'animasi' => 'ikut',
            'tema' => 'terang',
            'menu' => 'otomatis',
            'beranda' => match ($u->peranAktif()) {
                P::PIMPINAN => 'dashboard',
                P::DTI => 'log',
                P::ADMIN => 'master',
                default => 'rekomendasi',
            },
        ];
    }

    /** Pengaturan akun ini: pilihannya sendiri, ditimpakan ke bawaan perannya. */
    public static function untuk(User $u): array
    {
        $bawaan = self::bawaan($u);

        return array_merge($bawaan, array_intersect_key((array) ($u->pengaturan ?? []), $bawaan));
    }

    /** Halaman pertama yang boleh untuk peran ini. */
    public static function berandaBoleh(User $u): array
    {
        return array_keys(array_filter(self::BERANDA,
            fn ($b) => ! isset($b['peran']) || in_array($u->peranAktif(), $b['peran'], true)));
    }

    /** Rute halaman pertama — pilihannya, kalau masih boleh untuk perannya sekarang. */
    public static function ruteBeranda(User $u): string
    {
        $b = self::untuk($u)['beranda'];
        if (! in_array($b, self::berandaBoleh($u), true)) {
            $b = self::bawaan($u)['beranda'];
        }

        return self::BERANDA[$b]['rute'];
    }

    /** Sebutan nilainya untuk dibaca orang: "aktif", "dikurangi", "Dashboard". */
    public static function sebut(string $kunci, mixed $v): string
    {
        return match ($kunci) {
            'popup', 'email', 'pembaruan' => $v ? 'aktif' : 'nonaktif',
            'animasi' => $v === 'kurang' ? 'dikurangi' : 'ikuti perangkat',
            'tema' => $v === 'gelap' ? 'Gelap' : 'Terang',
            'menu' => ['otomatis' => 'Otomatis', 'lebar' => 'Selalu lebar', 'ringkas' => 'Selalu ringkas'][$v] ?? (string) $v,
            'beranda' => self::BERANDA[$v]['nama'] ?? (string) $v,
            default => (string) $v,
        };
    }

    /**
     * Simpan pilihan baru; hanya yang berubah dicatat di log. False bila tidak ada yang berubah.
     */
    public static function simpan(User $u, array $baru, string $bagian): bool
    {
        // Dua tab bisa menyimpan bersamaan: gabungkan dengan nilai terbaru yang dikunci.
        return DB::transaction(function () use ($u, $baru, $bagian) {
            $terkini = User::whereKey($u->id)->lockForUpdate()->firstOrFail();
            $lama = self::untuk($terkini);
            $beda = array_keys(array_filter($baru, fn ($v, $k) => array_key_exists($k, $lama) && $lama[$k] !== $v,
                ARRAY_FILTER_USE_BOTH));
            if (! $beda) {
                $u->pengaturan = $terkini->pengaturan;

                return false;
            }

            $terkini->forceFill(['pengaturan' => array_merge((array) ($terkini->pengaturan ?? []), array_intersect_key($baru, array_flip($beda)))])->save();
            $u->pengaturan = $terkini->pengaturan;
            Aktivitas::catat('akun.setelan', 'Mengubah pengaturan '.$bagian, [
                'kelompok' => 'akun',
                'rincian' => [
                    'sebelum' => array_intersect_key($lama, array_flip($beda)),
                    'sesudah' => array_intersect_key($baru, array_flip($beda)),
                ],
            ]);

            return true;
        });
    }
}
