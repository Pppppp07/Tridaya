<?php

namespace App\Support;

use App\Enums\SumberLaporan;
use Illuminate\Http\Request;

/** Lingkup yang dilihat (Semua, LHP, LHA): disimpan di sesi dan berlaku di semua layar. */
class Lingkup
{
    private const KUNCI = 'lingkup';

    /** Baca dari alamat kalau disebut (dan simpan), selain itu dari sesi. */
    public static function dari(?Request $req = null): string
    {
        $req ??= request();
        $diminta = $req->query('lingkup');
        if (in_array($diminta, ['semua', 'LHP', 'LHA'], true)) {
            session([self::KUNCI => $diminta]);

            return $diminta;
        }

        return session(self::KUNCI, 'semua');
    }

    public static function berlaku(string $lingkup, SumberLaporan $jenis): bool
    {
        return $lingkup === 'semua' || $lingkup === $jenis->value;
    }
}
