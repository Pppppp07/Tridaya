<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun yang dinonaktifkan keluar di permintaan berikutnya, bukan menunggu
 * sesinya habis. Terjadi saat Setba mengganti penanggung jawab sebuah unit
 * kerja: yang lama tidak boleh lagi melihat pekerjaan unit itu — "yang bisa
 * lihat tuh hanya dia doang".
 */
class PastikanAkunAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if ($u && ! $u->aktif) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('masuk')->withErrors([
                'email' => 'Akun ini sudah tidak aktif. Unit kerjanya kini dipegang penanggung jawab lain.',
            ]);
        }

        return $next($request);
    }
}
