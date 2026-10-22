<?php

namespace App\Http\Middleware;

use App\Support\Sesi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Keluar otomatis sesudah SIMTLHP_BATAS_DIAM menit tanpa aktivitas. */
class BatasDiam
{
    /** Permintaan latar (cek lonceng tiap menit, cek sisa waktu) tidak dihitung sebagai aktivitas. */
    private const LATAR = ['pemberitahuan.ringkas', 'sesi.sisa', 'sesi.berakhir'];

    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->hasSession() ? $request->user() : null;
        if (! $u) {
            return $next($request);
        }

        $kini = Sesi::detikKini();
        $terakhir = Sesi::aktivitasTerakhir($request);
        if ($terakhir && $kini - $terakhir >= Sesi::batasDiam()) {
            return Sesi::akhiriKarenaDiam($request, $u, $kini - $terakhir);
        }
        if (! $terakhir || ! $request->routeIs(...self::LATAR)) {
            Sesi::catatAktivitas($request, $kini);
        }

        return $next($request);
    }
}
