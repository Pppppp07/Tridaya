<?php

namespace App\Support\Sso;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * SSO lewat OpenID Connect / OAuth 2.0 — alur "authorization code" dengan
 * PKCE (27 Sep). `SIMTLHP_SSO=oidc`.
 *
 * Yang diisi dari Pusdatin (config simtlhp.sso.oidc): alamat authorize, token,
 * userinfo, dan (bila ada) logout; client id dan secret; nama klaim NIP dan
 * kawan-kawannya. Alamat kembali yang didaftarkan ke mereka:
 * {APP_URL}/masuk/sso/kembali.
 *
 * Penjaga di sepanjang jalan:
 * - `state` acak sekali pakai, dicocokkan saat kembali — link kembali
 *   palsu dari luar (CSRF masuk) ditolak;
 * - PKCE (S256): kode yang tercegat di jalan tidak bisa ditukar orang lain;
 * - `nonce` dicocokkan dengan id_token bila penyedianya mengirimkannya;
 * - kode ditukar lewat jalur belakang server-ke-server, dengan batas waktu,
 *   jadi token tidak pernah lewat browser;
 * - perjalanan yang terlalu lama (lebih dari 10 menit) harus diulang.
 *
 * Tanda tangan id_token tidak diperiksa: klaim dibaca dari userinfo, yang
 * diambil langsung dari penyedia lewat TLS dengan access token — boleh menurut
 * OpenID Connect Core 3.1.3.7. Bila Pusdatin mewajibkan pemeriksaan JWKS,
 * tambahkan di `klaimIdToken()`.
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
            'response_type'         => 'code',
            'client_id'             => $c['client_id'],
            'redirect_uri'          => $this->alamatKembali(),
            'scope'                 => $c['scope'] ?: 'openid',
            'state'                 => $state,
            'nonce'                 => $nonce,
            'code_challenge'        => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away($c['authorize'].(str_contains($c['authorize'], '?') ? '&' : '?').$q);
    }

    public function terima(Request $r): Identitas
    {
        $c = $this->konfigurasi();
        $sesi = (array) $r->session()->pull(self::SESI, []);

        if ($r->filled('error')) {
            throw new GagalSso('Masuk lewat SSO dibatalkan atau ditolak penyedia SSO.', [
                'error' => (string) $r->query('error'), 'keterangan' => Str::limit((string) $r->query('error_description'), 200),
            ]);
        }
        if (! $sesi || ! hash_equals((string) ($sesi['state'] ?? ''), (string) $r->query('state'))) {
            throw new GagalSso('Link masuk ini tidak valid atau sudah dipakai. Ulangi dari tombol Masuk dengan SSO.', ['sebab' => 'state']);
        }
        if (time() - (int) ($sesi['mulai'] ?? 0) > self::UMUR_DETIK) {
            throw new GagalSso('Perjalanan masuk terlalu lama. Ulangi dari tombol Masuk dengan SSO.', ['sebab' => 'kedaluwarsa']);
        }
        $kode = (string) $r->query('code');
        if ($kode === '') {
            throw new GagalSso('Penyedia SSO tidak mengirim kode masuk.', ['sebab' => 'tanpa kode']);
        }

        $token = $this->tukarKode($c, $kode, (string) $sesi['verifier']);
        $idToken = $this->klaimIdToken((string) ($token['id_token'] ?? ''), $c, (string) $sesi['nonce']);
        $klaim = $c['userinfo']
            ? $this->userinfo($c, (string) ($token['access_token'] ?? '')) + $idToken
            : $idToken;
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
            'client_id'                => (string) config('simtlhp.sso.oidc.client_id'),
            'post_logout_redirect_uri' => route('masuk'),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /* ================================================================ */

    private function konfigurasi(): array
    {
        $c = (array) config('simtlhp.sso.oidc');
        foreach (['authorize', 'token', 'client_id'] as $wajib) {
            if (empty($c[$wajib])) {
                throw new GagalSso('SSO belum dikonfigurasi lengkap di server ini. Hubungi admin aplikasi.', ['kurang' => $wajib]);
            }
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
            'grant_type'    => 'authorization_code',
            'code'          => $kode,
            'redirect_uri'  => $this->alamatKembali(),
            'client_id'     => $c['client_id'],
            'code_verifier' => $verifier,
        ];
        if (! empty($c['client_secret'])) {
            $isi['client_secret'] = $c['client_secret'];
        }

        try {
            $jwb = Http::asForm()->acceptJson()->timeout(max(2, (int) $c['batas_detik']))
                ->post($c['token'], $isi);
        } catch (ConnectionException $e) {
            throw new GagalSso('Server SSO tidak dapat dihubungi. Coba lagi beberapa saat lagi.', ['sebab' => 'token tak terjangkau']);
        }
        if (! $jwb->successful() || ! is_array($jwb->json())) {
            throw new GagalSso('Penyedia SSO menolak kode masuk.', ['status' => $jwb->status(), 'error' => $jwb->json('error')]);
        }

        return (array) $jwb->json();
    }

    private function userinfo(array $c, string $akses): array
    {
        if ($akses === '') {
            throw new GagalSso('Penyedia SSO tidak mengirim token akses.', ['sebab' => 'tanpa access_token']);
        }
        try {
            $jwb = Http::withToken($akses)->acceptJson()->timeout(max(2, (int) $c['batas_detik']))->get($c['userinfo']);
        } catch (ConnectionException $e) {
            throw new GagalSso('Server SSO tidak dapat dihubungi. Coba lagi beberapa saat lagi.', ['sebab' => 'userinfo tak terjangkau']);
        }
        if (! $jwb->successful() || ! is_array($jwb->json())) {
            throw new GagalSso('Identitas pegawai tidak bisa diambil dari SSO.', ['status' => $jwb->status()]);
        }

        return (array) $jwb->json();
    }

    /**
     * Isi id_token (tanpa memeriksa tanda tangannya — lihat catatan kelas).
     * Yang diperiksa: untuk aplikasi ini (`aud`), belum kedaluwarsa (`exp`),
     * dan `nonce`-nya milik perjalanan masuk ini.
     */
    private function klaimIdToken(string $jwt, array $c, string $nonce): array
    {
        if ($jwt === '') {
            return [];
        }
        $bagian = explode('.', $jwt);
        $isi = count($bagian) === 3 ? json_decode((string) base64_decode(strtr($bagian[1], '-_', '+/')), true) : null;
        if (! is_array($isi)) {
            throw new GagalSso('Token identitas dari SSO rusak.', ['sebab' => 'id_token']);
        }
        $aud = (array) ($isi['aud'] ?? []);
        if (! in_array($c['client_id'], $aud, true)) {
            throw new GagalSso('Token identitas bukan untuk aplikasi ini.', ['sebab' => 'aud']);
        }
        if (isset($isi['exp']) && (int) $isi['exp'] < time() - 60) {
            throw new GagalSso('Token identitas sudah kedaluwarsa. Ulangi masuk.', ['sebab' => 'exp']);
        }
        if (isset($isi['nonce']) && ! hash_equals($nonce, (string) $isi['nonce'])) {
            throw new GagalSso('Token identitas tidak cocok dengan perjalanan masuk ini.', ['sebab' => 'nonce']);
        }

        return $isi;
    }
}
