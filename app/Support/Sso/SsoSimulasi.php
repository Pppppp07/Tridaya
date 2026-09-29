<?php

namespace App\Support\Sso;

use App\Support\DirektoriIrm;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SSO tiruan untuk demo (27 Sep) — `SIMTLHP_SSO=simulasi`.
 *
 * "Halaman masuk SSO"-nya daftar pegawai dari direktori: pilih seseorang,
 * dan aplikasi menerimanya seolah-olah penyedia SSO baru saja membuktikan
 * orang itu. Sesudah titik itu SEMUANYA sungguhan: pemeriksaan NIP, peran,
 * akun nonaktif, penyamaan atribut, log aktivitas, halaman Akses dibatasi.
 *
 * Tidak pernah menyala di produksi (Penyedia::driver()).
 */
class SsoSimulasi implements PenyediaSso
{
    public function arahkan(Request $r): Response
    {
        return redirect()->route('sso.simulasi');
    }

    public function terima(Request $r): Identitas
    {
        $p = DirektoriIrm::nip((string) $r->input('nip'));
        if (! $p) {
            throw new GagalSso('Pegawai itu tidak ada di direktori.', ['nip' => (string) $r->input('nip')]);
        }

        return Identitas::dariPegawai($p);
    }

    public function alamatKeluar(): ?string
    {
        return null;
    }
}
