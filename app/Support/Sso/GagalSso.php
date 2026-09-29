<?php

namespace App\Support\Sso;

use RuntimeException;

/**
 * Perjalanan masuk lewat SSO gagal di tengah jalan. Pesannya ditampilkan ke
 * orangnya, jadi ditulis dalam bahasa sehari-hari; rinciannya yang teknis
 * masuk ke log aktivitas lewat `$rincian`.
 */
class GagalSso extends RuntimeException
{
    public function __construct(string $pesan, public readonly array $rincian = [])
    {
        parent::__construct($pesan);
    }
}
