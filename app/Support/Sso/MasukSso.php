<?php

namespace App\Support\Sso;

use App\Models\User;
use App\Support\Aktivitas;
use App\Support\Akun;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Sesi aplikasi dan hak lokal sama untuk OIDC maupun JWT Dwaradaya. */
class MasukSso
{
    public static function jalankan(Request $r, User $u, Identitas $id): void
    {
        $beda = Otorisasi::samakan($u, $id);
        Auth::login($u);
        $r->session()->regenerate();
        $r->session()->put('masuk_lewat_sso', true);
        Akun::tandaiMasuk($u, 'sso');
        if ($beda) {
            Aktivitas::catat('masuk.sso.samakan', 'Atribut akun disamakan dengan identitas SSO', [
                'oleh' => $u, 'subjek' => $u, 'rincian' => $beda,
            ]);
        }
    }
}
