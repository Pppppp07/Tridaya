<?php

namespace App\Support\Sso;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Kontrak penyedia SSO; keputusan hak akses ada di Otorisasi. */
interface PenyediaSso
{
    /** Awal perjalanan masuk: bawa orangnya ke halaman masuk penyedia SSO. */
    public function arahkan(Request $r): Response;

    /**
     * Jawaban penyedia di alamat kembali → Identitas.
     * @throws GagalSso bila tidak sah, kedaluwarsa, atau ditolak
     */
    public function terima(Request $r): Identitas;

    /** Alamat keluar di penyedia SSO, atau null bila tidak ada. */
    public function alamatKeluar(): ?string;
}
