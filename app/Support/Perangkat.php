<?php

namespace App\Support;

/**
 * "Edge · Windows" dari User-Agent (28 Sep) — padanan `sebutPerangkat`
 * prototipe. Dipakai daftar perangkat yang sedang masuk dan riwayat masuk di
 * Profil → Keamanan.
 *
 * Cukup untuk dikenali orangnya sendiri, bukan sidik perangkat. Urutannya
 * penting: Edge dan Opera ikut menulis "Chrome", Android ikut menulis "Linux".
 */
class Perangkat
{
    /** @return array{nama:string, ponsel:bool} */
    public static function sebut(?string $ua): array
    {
        $s = (string) $ua;
        $browser = match (true) {
            (bool) preg_match('~Edg(e|A|iOS)?/~', $s)  => 'Edge',
            (bool) preg_match('~OPR/|Opera~', $s)       => 'Opera',
            str_contains($s, 'SamsungBrowser')          => 'Samsung Internet',
            (bool) preg_match('~Firefox/|FxiOS~', $s)   => 'Firefox',
            (bool) preg_match('~Chrome/|CriOS~', $s)    => 'Chrome',
            str_contains($s, 'Safari/')                 => 'Safari',
            default                                     => '',
        };
        $sistem = match (true) {
            str_contains($s, 'Windows')                    => 'Windows',
            str_contains($s, 'Android')                    => 'Android',
            (bool) preg_match('~iPhone|iPad|iPod~', $s)    => 'iOS',
            (bool) preg_match('~Mac OS X|Macintosh~', $s)  => 'macOS',
            str_contains($s, 'CrOS')                       => 'ChromeOS',
            str_contains($s, 'Linux')                      => 'Linux',
            default                                        => '',
        };
        $nama = implode(' · ', array_filter([$browser, $sistem]));

        return [
            'nama'   => $nama !== '' ? $nama : 'Perangkat tidak dikenal',
            'ponsel' => (bool) preg_match('~Mobi|Android|iPhone|iPod~', $s),
        ];
    }
}
