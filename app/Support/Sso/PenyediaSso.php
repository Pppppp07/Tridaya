<?php

namespace App\Support\Sso;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kontrak satu penyedia SSO (27 Sep).
 *
 * Tiga tugas, tidak lebih. Semua keputusan hak akses ada di luar kelas ini
 * (Otorisasi), jadi penyedia yang berbeda — OIDC, SAML, atau API eHRM khusus
 * Pusdatin — cukup menulis ketiga cara ini lalu didaftarkan di
 * config simtlhp.sso.kelas. Pengendali, halaman, dan aturannya tidak berubah.
 */
interface PenyediaSso
{
    /** Awal perjalanan masuk: bawa orangnya ke halaman masuk penyedia SSO. */
    public function arahkan(Request $r): Response;

    /**
     * Jawaban penyedia di alamat kembali → siapa orangnya.
     *
     * @throws GagalSso bila jawabannya tidak sah, kedaluwarsa, atau ditolak
     */
    public function terima(Request $r): Identitas;

    /** Alamat keluar di penyedia SSO, atau null bila tidak ada. */
    public function alamatKeluar(): ?string;
}
