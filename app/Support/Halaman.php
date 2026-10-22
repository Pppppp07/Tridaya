<?php

namespace App\Support;

class Halaman
{
    /** Potongan hanya untuk permintaan skrip yang secara eksplisit memintanya. */
    public static function bagian(): bool
    {
        return request()->ajax() && request()->header('X-Simtlhp-Fragment') === 'isi';
    }
}
