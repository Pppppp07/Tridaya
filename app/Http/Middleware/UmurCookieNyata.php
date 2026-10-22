<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tanggal demo memundurkan Carbon::now(), jadi cookie tiba kedaluwarsa menurut jam browser. Sisa
 * umur cookie digeser ke jam asli; tanpa tanggal demo tidak ada yang berubah.
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
