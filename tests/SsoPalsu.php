<?php

namespace Tests;

use App\Support\DirektoriIrm;
use App\Support\Sso\GagalSso;
use App\Support\Sso\Identitas;
use App\Support\Sso\PenyediaSso;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penyedia SSO palsu khusus uji: NIP dikirim langsung ke /masuk/sso/kembali, sesudah itu semua
 * pemeriksaan sungguhan (Otorisasi). Tidak ada di aplikasi.
 */
class SsoPalsu implements PenyediaSso
{
    public static function pasang(): void
    {
        config(['simtlhp.sso.kelas' => ['palsu' => self::class], 'simtlhp.sso.driver' => 'palsu']);
    }

    public function arahkan(Request $r): Response
    {
        return redirect()->route('sso.kembali');
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
