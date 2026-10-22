<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun yang hanya melihat (DTI, Pimpinan) tidak bisa mengirim isian; ditolak di pintu dan tercatat.
 * Pengecualian: urusan akunnya sendiri.
 */
class HanyaMelihat
{
    private const BOLEH = ['keluar', 'pemberitahuan.*', 'akun.*', 'sesi.*', 'sso.dwaradaya.lanjutkan'];

    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if ($u && $u->peranAktif()?->hanyaMelihat() && ! $request->isMethodSafe()
            && ! Str::is(self::BOLEH, (string) $request->route()?->getName())) {
            abort(403, 'Akun ini hanya untuk melihat.');
        }

        return $next($request);
    }
}
