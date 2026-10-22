<?php

namespace App\Aksi;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Berkas sudah diubah orang lain sejak halaman dibaca (klik ganda, dua petugas bersamaan): aksinya
 * dibatalkan supaya satu berkas tidak diproses dua kali.
 */
class BerkasBerubah extends ConflictHttpException
{
    public const PESAN = 'Berkas ini baru saja diproses orang lain. Keadaan terbarunya sudah tampil; periksa lagi sebelum melanjutkan.';

    public function __construct()
    {
        parent::__construct(self::PESAN);
    }
}
