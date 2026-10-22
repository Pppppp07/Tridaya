<?php

namespace App\Http\Middleware;

use Illuminate\Session\Middleware\StartSession;

/** URL masuk SuperApp membawa kredensial dan tidak boleh menjadi URL "kembali". */
class SesiTanpaToken extends StartSession
{
    protected function storeCurrentUrl($request, $session)
    {
        if (! $request->is('sso-login')) {
            parent::storeCurrentUrl($request, $session);
        }
    }
}
