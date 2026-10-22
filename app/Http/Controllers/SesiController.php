<?php

namespace App\Http\Controllers;

use App\Support\Sesi;
use Illuminate\Http\Request;

/** Dipakai simtlhp-sesi.js untuk peringatan dan keluar otomatis. */
class SesiController extends Controller
{
    /** GET: sisa waktu tanpa memperpanjang. POST ("Tetap masuk"): BatasDiam sudah memperpanjangnya. */
    public function sisa(Request $r)
    {
        return response()->json(['sisa' => Sesi::sisaDiam($r), 'batas' => Sesi::batasDiam()]);
    }

    /** Tujuan skrip saat waktu habis. */
    public function berakhir(Request $r)
    {
        if ($r->user()) {
            // Menurut server belum habis (mis. tab lain masih dipakai).
            return redirect()->to(Sesi::alamatKembali($r) ?? route('beranda'));
        }
        Sesi::tandaiBerakhir($r, Sesi::alamatKembali($r));

        return redirect()->route('masuk');
    }
}
