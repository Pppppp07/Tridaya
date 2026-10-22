<?php

namespace App\Support\Sso;

use RuntimeException;

/**
 * Masuk lewat SSO gagal di tengah jalan. Pesannya untuk orangnya (bahasa sehari-hari); rincian
 * teknis ke log lewat `$rincian`.
 */
class GagalSso extends RuntimeException
{
    public function __construct(string $pesan, public readonly array $rincian = [])
    {
        parent::__construct($pesan);
    }
}
