<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Tamu diarahkan ke halaman masuk kita sendiri, bukan route bawaan 'login'.
        $middleware->redirectGuestsTo(fn () => route('masuk'));
        $middleware->redirectUsersTo(fn () => route('beranda'));
        // Proksi yang dipercaya dibaca dari config (27 Sep) — alamat asli
        // pengguna untuk log aktivitas, dan tahu bila dibuka lewat HTTPS.
        $middleware->replace(\Illuminate\Http\Middleware\TrustProxies::class, \App\Http\Middleware\PercayaiProxy::class);
        // Akun yang dinonaktifkan — penanggung jawab yang diganti — keluar di
        // permintaan berikutnya. Umur cookie dihitung dari jam sungguhan,
        // bukan tanggal demo (lihat UmurCookieNyata) — paling luar,
        // supaya semua cookie kelompok web sudah terpasang.
        //
        // 27 Sep: kepala keamanan (CSP, anti-cache halaman berlogin) paling
        // luar sesudahnya; log aktivitas membungkus penjaga akun "hanya
        // melihat", supaya penolakannya ikut tercatat.
        $middleware->web(
            append: [
                \App\Http\Middleware\PastikanAkunAktif::class,
                \App\Http\Middleware\CatatAktivitas::class,
                \App\Http\Middleware\HanyaMelihat::class,
            ],
            prepend: [
                \App\Http\Middleware\UmurCookieNyata::class,
                \App\Http\Middleware\KepalaAman::class,
            ],
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
