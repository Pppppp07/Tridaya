<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun yang hanya melihat — DTI dan Pimpinan — tidak bisa mengirim isian apa
 * pun (27 Sep). Bang Kamal tentang DTI: "Kalau DTI itu hanya melihat view."
 *
 * Tombolnya memang tidak digambar untuk mereka, tapi yang menjaga ini: formulir
 * yang dikirim dari luar layar pun ditolak di pintu, dan penolakannya tercatat
 * di log (CatatAktivitas). Yang tetap boleh hanya urusan akunnya sendiri:
 * keluar, menandai pemberitahuannya, dan Profil & pengaturan — password,
 * perangkat yang sedang masuk, pengaturan pemberitahuan dan tampilan (28 Sep).
 */
class HanyaMelihat
{
    private const BOLEH = ['keluar', 'pemberitahuan.*', 'akun.*'];

    public function handle(Request $request, Closure $next): Response
    {
        $u = $request->user();
        if ($u && $u->peran?->hanyaMelihat() && ! $request->isMethodSafe()
            && ! Str::is(self::BOLEH, (string) $request->route()?->getName())) {
            abort(403, 'Akun ini hanya untuk melihat.');
        }

        return $next($request);
    }
}
