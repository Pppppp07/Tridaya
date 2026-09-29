<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;

/**
 * Proksi yang dipercaya (27 Sep).
 *
 * Di server pemerintah aplikasi biasanya berdiri di belakang penyeimbang
 * beban atau reverse proxy. Tanpa daftar ini, alamat yang tercatat di log
 * aktivitas selalu alamat proksinya — semua orang terlihat datang dari satu
 * mesin — dan aplikasi tidak tahu ia sebenarnya dibuka lewat HTTPS.
 *
 * Isi SIMTLHP_PROXY_TEPERCAYA dengan alamat proksinya (pisahkan dengan koma),
 * atau "*" bila hanya proksi itu yang bisa menjangkau aplikasi. Kosong = tidak
 * memercayai kepala X-Forwarded-* dari siapa pun — aman untuk server yang
 * langsung menghadap pengguna. Dibaca dari config, jadi tetap jalan sesudah
 * `php artisan config:cache`.
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
