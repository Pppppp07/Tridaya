<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;

/**
 * Proksi tepercaya (SIMTLHP_PROXY_TEPERCAYA, pisah koma, atau "*"): supaya IP asli tercatat dan
 * HTTPS dikenali. Kosong = tidak memercayai X-Forwarded-*.
 */
class PercayaiProxy extends TrustProxies
{
    protected function proxies()
    {
        $isi = trim((string) config('simtlhp.keamanan.proxy_tepercaya', ''));
        if ($isi === '') {
            return null;
        }

        return $isi === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $isi))));
    }
}
