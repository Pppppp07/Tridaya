<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tanggal demo (config/simtlhp.php) mematok Carbon::now() di 17 Agustus
 * 2026. Laravel menghitung umur cookie dari jam itu, sedangkan browser
 * membandingkannya dengan jam SUNGGUHAN — cookie sesi tiba dalam keadaan
 * kedaluwarsa (Max-Age=0) dan langsung dibuang. Akibatnya tiap halaman
 * membuka sesi baru: formulir ditolak (419 "Page Expired"), pop-up
 * pemberitahuan tampil lagi dengan hitung mundur dari awal di tiap halaman,
 * filter Dashboard terlupa. Masuknya sendiri bertahan hanya karena cookie
 * "ingat saya" berumur lima tahun.
 *
 * Di sini sisa umur tiap cookie dihitung dari jam aplikasi, lalu ditempelkan
 * pada jam sungguhan. Tanpa tanggal demo kedua jam sama dan tidak ada
 * yang diubah. Dipasang paling luar di kelompok web (bootstrap/app.php),
 * supaya cookie sesi, XSRF-TOKEN, dan "ingat saya" sudah terpasang semua.
 */
class UmurCookieNyata
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $geser = time() - Carbon::now()->getTimestamp();
        if (abs($geser) < 60) {
            return $response;
        }
        foreach ($response->headers->getCookies() as $c) {
            /* 0 = cookie sesi browser, hidup sampai browser-nya ditutup. */
            if ($c->getExpiresTime() > 0) {
                $response->headers->setCookie($c->withExpires($c->getExpiresTime() + $geser));
            }
        }

        return $response;
    }
}
