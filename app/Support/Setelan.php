<?php

namespace App\Support;

use App\Enums\PeranPengguna as P;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Pengaturan akun sendiri (28 Sep) — Profil & pengaturan → Pemberitahuan dan
 * Tampilan. Padanan `setelanBawaan`, `BERANDA_AKUN`, dan `simpanSetelan`
 * prototipe.
 *
 * Disimpan di `users.pengaturan` (JSON). Yang belum pernah dipilih mengikuti
 * bawaan perannya:
 * - popup  — pemberitahuan baru dimunculkan sebagai pop-up sekali di pojok kanan atas (nyala);
 * - email   — pemberitahuan dikirim juga ke email: nyala untuk penanggung jawab unit
 *             kerja (sejak 27 Sep pemberitahuannya memang dikirim ke email), mati untuk
 *             petugas pusat sampai ia menyalakannya sendiri;
 * - animasi — "ikut" pengaturan perangkat, atau "kurang": tanpa animasi;
 * - tema — terang (bawaan) atau gelap, diterapkan sebelum halaman digambar;
 * - menu — otomatis, lebar, atau ringkas, mengikuti akun di semua perangkat;
 * - beranda — halaman pertama sesudah masuk.
 */
class Setelan
{
    public const BERANDA = [
        'rekomendasi' => ['nama' => 'Rekomendasi', 'rute' => 'rekomendasi.index'],
        'laporan' => ['nama' => 'Daftar laporan', 'rute' => 'laporan.index'],
        'dashboard' => ['nama' => 'Dashboard', 'rute' => 'ringkasan', 'peran' => [P::SETBA, P::PIMPINAN, P::ADMIN]],
        'log' => ['nama' => 'Log aktivitas', 'rute' => 'log', 'peran' => [P::DTI, P::ADMIN]],
        'pemberitahuan' => ['nama' => 'Pemberitahuan', 'rute' => 'pemberitahuan'],
    ];

    /** Nama tiap pengaturan di log, sebelum → sesudah (bagian/beda-log). */
    public const NAMA = ['popup' => 'Pop-up pemberitahuan', 'email' => 'Email pemberitahuan', 'animasi' => 'Animasi', 'beranda' => 'Halaman pertama', 'tema' => 'Tema', 'menu' => 'Menu samping'];

    /** @return array{popup:bool, email:bool, animasi:string, beranda:string, tema:string, menu:string} */
    public static function bawaan(User $u): array
    {
        return [
            'popup' => true,
            'email' => $u->peran === P::SATKER,
            'animasi' => 'ikut',
            'tema' => 'terang',
            'menu' => 'otomatis',
            'beranda' => match ($u->peran) {
                P::PIMPINAN => 'dashboard',
                P::DTI => 'log',
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

    /**
     * Pilihan halaman pertama yang boleh untuk peran ini.
     *
     * @return list<string>
     */
    public static function berandaBoleh(User $u): array
    {
        return array_keys(array_filter(self::BERANDA,
            fn ($b) => ! isset($b['peran']) || in_array($u->peran, $b['peran'], true)));
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
            'popup', 'email' => $v ? 'aktif' : 'nonaktif',
            'animasi' => $v === 'kurang' ? 'dikurangi' : 'ikuti perangkat',
            'tema' => $v === 'gelap' ? 'Gelap' : 'Terang',
            'menu' => ['otomatis' => 'Otomatis', 'lebar' => 'Selalu lebar', 'ringkas' => 'Selalu ringkas'][$v] ?? (string) $v,
            'beranda' => self::BERANDA[$v]['nama'] ?? (string) $v,
            default => (string) $v,
        };
    }

    /**
     * Simpan pilihan baru. Hanya yang berubah yang dicatat di log, sebelum →
     * sesudah. False kalau tidak ada yang berubah.
     */
    public static function simpan(User $u, array $baru, string $bagian): bool
    {
        // Dua tab dapat menyimpan tema dan pengaturan lain bersamaan. Gabungkan
        // dengan nilai terbaru yang dikunci, bukan salinan model dari sesi awal.
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
