<?php

namespace App\Support\Sso;

use App\Rules\LinkAman;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * OIDC authorization code + PKCE (`SIMTLHP_SSO=oidc`). Alamat kembali: {APP_URL}/masuk/sso/kembali.
 * Penjaga: state sekali pakai (anti-CSRF masuk), PKCE S256, id_token RS256 diverifikasi JWKS dari
 * config, iss/aud/exp/sub/nonce wajib cocok, kode ditukar server-ke-server, perjalanan maks. 10
 * menit. UserInfo digabung hanya bila sub-nya cocok.
 */
class SsoOidc implements PenyediaSso
{
    private const SESI = 'sso.oidc';

    private const UMUR_DETIK = 600;

    public function arahkan(Request $r): Response
    {
        $c = $this->konfigurasi();
        $state = Str::random(40);
        $nonce = Str::random(40);
        $verifier = Str::random(96);
        $r->session()->put(self::SESI, [
            'state' => $state, 'nonce' => $nonce, 'verifier' => $verifier, 'mulai' => time(),
        ]);

        $q = http_build_query([
            'response_type' => 'code',
            'client_id' => $c['client_id'],
            'redirect_uri' => $this->alamatKembali(),
            'scope' => $c['scope'] ?: 'openid',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away($c['authorize'].(str_contains($c['authorize'], '?') ? '&' : '?').$q);
    }

    public function terima(Request $r): Identitas
    {
        $c = $this->konfigurasi();
        $sesi = (array) $r->session()->pull(self::SESI, []);

        $state = $r->query('state');
        if (! $sesi || ! is_string($state) || ! hash_equals((string) ($sesi['state'] ?? ''), $state)) {
            throw new GagalSso('Link masuk ini tidak valid atau sudah dipakai. Ulangi dari tombol Masuk dengan SSO.', ['sebab' => 'state']);
        }
        if (time() - (int) ($sesi['mulai'] ?? 0) > self::UMUR_DETIK) {
            throw new GagalSso('Perjalanan masuk terlalu lama. Ulangi dari tombol Masuk dengan SSO.', ['sebab' => 'kedaluwarsa']);
        }
        if ($r->filled('error')) {
            /* Hanya kode error yang dikenal, tanpa menyalin teks bebas penyedia. */
            throw new GagalSso('Masuk lewat SSO dibatalkan atau ditolak penyedia SSO.', ['sebab' => 'penyedia menolak']);
        }
        $kode = $r->query('code');
        if (! is_string($kode) || $kode === '' || strlen($kode) > 8192) {
            throw new GagalSso('Penyedia SSO tidak mengirim kode masuk.', ['sebab' => 'tanpa kode']);
        }

        $token = $this->tukarKode($c, $kode, (string) $sesi['verifier']);
        if (! is_string($token['id_token'] ?? null) || ! is_string($token['access_token'] ?? null)
            || ! is_string($token['token_type'] ?? null) || strcasecmp($token['token_type'], 'Bearer') !== 0) {
            throw new GagalSso('Penyedia SSO mengirim respons token yang tidak lengkap.', ['sebab' => 'respons token']);
        }
        $idToken = (new TokenIdentitas)->periksa($token['id_token'], $c, (string) $sesi['nonce']);
        $klaim = $idToken;
        if (! empty($c['userinfo'])) {
            $info = $this->userinfo($c, $token['access_token']);
            if (! is_string($info['sub'] ?? null) || ! hash_equals($idToken['sub'], $info['sub'])) {
                throw new GagalSso('Identitas pegawai dari SSO tidak cocok.', ['sebab' => 'userinfo sub']);
            }
            $klaim = $info + $idToken;
        }
        if (! $klaim) {
            throw new GagalSso('Penyedia SSO tidak mengirim identitas pegawai.', ['sebab' => 'tanpa klaim']);
        }

        $id = Identitas::dariKlaim($klaim, (array) $c['klaim']);
        if ($id->nip === '') {
            throw new GagalSso('Identitas dari SSO tidak membawa NIP.', ['klaim' => array_keys($klaim)]);
        }

        return $id;
    }

    public function alamatKeluar(): ?string
    {
        $ke = (string) config('simtlhp.sso.oidc.logout');
        if ($ke === '') {
            return null;
        }

        return $ke.(str_contains($ke, '?') ? '&' : '?').http_build_query([
            'client_id' => (string) config('simtlhp.sso.oidc.client_id'),
            'post_logout_redirect_uri' => route('masuk'),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /* ================================================================ */

    private function konfigurasi(): array
    {
        $c = (array) config('simtlhp.sso.oidc');
        foreach (['authorize', 'token', 'client_id', 'issuer', 'jwks'] as $wajib) {
            if (empty($c[$wajib])) {
                throw new GagalSso('SSO belum dikonfigurasi lengkap di server ini. Hubungi admin aplikasi.', ['kurang' => $wajib]);
            }
        }
        foreach (['authorize', 'token', 'userinfo', 'issuer', 'jwks', 'logout'] as $alamat) {
            if (empty($c[$alamat])) {
                continue;
            }
            $p = parse_url($c[$alamat]);
            if (! LinkAman::sah($c[$alamat]) || strtolower($p['scheme'] ?? '') !== 'https'
                || isset($p['user']) || isset($p['pass']) || isset($p['fragment'])) {
                throw new GagalSso('Alamat penyedia SSO harus HTTPS dan valid.', ['sebab' => 'konfigurasi', 'medan' => $alamat]);
            }
        }
        if (! in_array('openid', preg_split('/\s+/', trim((string) ($c['scope'] ?? ''))), true)) {
            throw new GagalSso('Scope SSO harus menyertakan openid.', ['sebab' => 'scope']);
        }

        return $c;
    }

    private function alamatKembali(): string
    {
        return (string) (config('simtlhp.sso.oidc.redirect') ?: route('sso.kembali'));
    }

    private function tukarKode(array $c, string $kode, string $verifier): array
    {
        $isi = [
            'grant_type' => 'authorization_code',
            'code' => $kode,
            'redirect_uri' => $this->alamatKembali(),
            'client_id' => $c['client_id'],
            'code_verifier' => $verifier,
        ];
        if (! empty($c['client_secret'])) {
            $isi['client_secret'] = $c['client_secret'];
        }

        try {
            $jwb = Http::asForm()->acceptJson()->withoutRedirecting()->timeout(max(2, (int) $c['batas_detik']))
                ->post($c['token'], $isi);
        } catch (ConnectionException $e) {
            throw new GagalSso('Server SSO tidak dapat dihubungi. Coba lagi beberapa saat lagi.', ['sebab' => 'token tak terjangkau']);
        }
        if (! $jwb->successful() || ! is_array($jwb->json())) {
            throw new GagalSso('Penyedia SSO menolak kode masuk.', ['status' => $jwb->status()]);
        }

        return (array) $jwb->json();
    }

    private function userinfo(array $c, string $akses): array
    {
        if ($akses === '') {
            throw new GagalSso('Penyedia SSO tidak mengirim token akses.', ['sebab' => 'tanpa access_token']);
        }
        try {
            $jwb = Http::withToken($akses)->acceptJson()->withoutRedirecting()->timeout(max(2, (int) $c['batas_detik']))->get($c['userinfo']);
        } catch (ConnectionException $e) {
            throw new GagalSso('Server SSO tidak dapat dihubungi. Coba lagi beberapa saat lagi.', ['sebab' => 'userinfo tak terjangkau']);
        }
        if (! $jwb->successful() || ! is_array($jwb->json())) {
            throw new GagalSso('Identitas pegawai tidak bisa diambil dari SSO.', ['status' => $jwb->status()]);
        }

        return (array) $jwb->json();
    }
}
