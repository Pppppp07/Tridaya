<?php

namespace App\Support\Sso;

use App\Rules\LinkAman;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pintu utama berasal dari kartu aplikasi di Dwaradaya, bukan authorization code OIDC. */
class SsoDwaradaya implements PenyediaSso
{
    public function arahkan(Request $r): Response
    {
        $url = $this->alamatPortal();
        if (! $url) {
            throw new GagalSso('Buka infaX dari menu aplikasi Dwaradaya. Alamat portal belum diatur di server ini.', ['sebab' => 'alamat portal']);
        }

        return redirect()->away($url);
    }

    public function terima(Request $r): Identitas
    {
        // /masuk/sso/kembali adalah callback OIDC. Dwaradaya menggunakan /sso-login.
        throw new GagalSso('Buka kembali infaX dari menu aplikasi Dwaradaya.', ['sebab' => 'pintu dwaradaya']);
    }

    public function alamatPortal(): ?string
    {
        return $this->alamat((string) config('simtlhp.sso.dwaradaya.portal'));
    }

    public function alamatKeluar(): ?string
    {
        // Tidak menebak endpoint logout global SuperApp; kosong berarti keluar lokal saja.
        return $this->alamat((string) config('simtlhp.sso.dwaradaya.logout'));
    }

    private function alamat(string $url): ?string
    {
        $p = parse_url($url);

        return LinkAman::sah($url) && strtolower($p['scheme'] ?? '') === 'https'
            && ! isset($p['user']) && ! isset($p['pass'])
            && ! isset($p['fragment']) ? $url : null;
    }
}
