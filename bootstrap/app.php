<?php

use App\Aksi\BerkasBerubah;
use App\Http\Middleware\BatasDiam;
use App\Http\Middleware\CatatAktivitas;
use App\Http\Middleware\HanyaMelihat;
use App\Http\Middleware\KepalaAman;
use App\Http\Middleware\PastikanAkunAktif;
use App\Http\Middleware\PercayaiProxy;
use App\Http\Middleware\SesiTanpaToken;
use App\Http\Middleware\UmurCookieNyata;
use App\Support\Sesi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Tamu diarahkan ke halaman masuk kita sendiri, bukan route bawaan 'login'.
        $middleware->redirectGuestsTo(fn () => route('masuk'));
        $middleware->redirectUsersTo(fn () => route('beranda'));
        // Proksi tepercaya dari config: IP asli untuk log, dan tahu bila lewat HTTPS.
        $middleware->replace(TrustProxies::class, PercayaiProxy::class);
        // Paling luar: UmurCookieNyata (umur cookie dari jam asli, bukan tanggal demo), lalu
        // KepalaAman (CSP, anti-cache). CatatAktivitas membungkus HanyaMelihat supaya penolakannya
        // tercatat.
        $middleware->web(
            append: [
                BatasDiam::class,
                PastikanAkunAktif::class,
                CatatAktivitas::class,
                HanyaMelihat::class,
            ],
            prepend: [
                UmurCookieNyata::class,
                KepalaAman::class,
            ],
            replace: [StartSession::class => SesiTanpaToken::class],
        );
        $middleware->api(prepend: [KepalaAman::class]);
        /* CatatAktivitas membungkus throttle supaya penolakan 429 ikut tercatat. */
        $middleware->prependToPriorityList(
            ThrottleRequests::class,
            CatatAktivitas::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['token']);
        // JWT tidak boleh muncul di halaman debug atau jejak exception.
        $exceptions->dontReportWhen(fn (Throwable $e) => request()->is('api/sso-check', 'sso-login'));
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/sso-check', 'sso-login')) {
                return null;
            }
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            if ($status >= 500) {
                // Catat jenis error saja, tanpa pesan, permintaan, atau argumen sensitif.
                Log::error('Layanan SSO Dwaradaya gagal memproses permintaan.', ['jenis' => $e::class]);
            }
            $pesan = match ($status) {
                404 => 'SSO Dwaradaya tidak tersedia.',
                405 => 'Metode permintaan tidak sesuai.',
                429 => 'Terlalu banyak permintaan. Tunggu sebentar, lalu coba lagi.',
                default => 'Permintaan SSO tidak dapat diproses. Buka kembali Rekala dari Dwaradaya atau hubungi Admin DTI.',
            };
            $headers = ['Cache-Control' => 'no-store, private', 'Referrer-Policy' => 'no-referrer']
                + ($e instanceof HttpExceptionInterface ? $e->getHeaders() : []);

            return $request->is('api/sso-check')
                ? response()->json(['error' => $pesan], $status, $headers)
                : response($pesan, $status, $headers);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Batas terlewati: diberi pesan di halamannya, bukan halaman 429.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }
            // Jawabannya pengalihan, tetapi Log admin tetap mencatatnya sebagai 429 (CatatAktivitas).
            $request->attributes->set(CatatAktivitas::DIBATASI, true);
            if (! $request->user()) {
                return $request->is('masuk', 'masuk/sso', 'masuk/sso/*')
                    ? redirect()->route('masuk')->withInput($request->only('email'))
                        ->withErrors(['email' => 'Terlalu banyak percobaan masuk dari jaringan ini. Tunggu sebentar, lalu coba lagi.'])
                    : null;
            }

            return back()->withInput($request->except('_token', 'password'))
                ->with('gagal', 'Terlalu banyak permintaan dalam waktu singkat. Tunggu sebentar, lalu coba lagi.');
        });
        // Berkas diproses orang lain di antara baca dan kirim: kembali dengan pesan, bukan halaman error.
        $exceptions->render(function (BerkasBerubah $e, Request $request) {
            return $request->expectsJson()
                ? response()->json(['pesan' => $e->getMessage()], 409)
                : back()->with('gagal', $e->getMessage());
        });
        // 419 (token formulir kedaluwarsa): pengganti halaman "Page Expired".
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }
            if (! $request->user()) {
                Sesi::tandaiBerakhir($request, Sesi::alamatKembali($request));

                return redirect()->route('masuk');
            }

            return back()->withInput($request->except('_token'))
                ->with('gagal', 'Halaman ini sudah terlalu lama dibuka. Periksa isian, lalu kirim lagi.');
        });
    })->create();
