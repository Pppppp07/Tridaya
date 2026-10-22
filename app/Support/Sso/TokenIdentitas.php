<?php

namespace App\Support\Sso;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Token hanya dipercaya setelah verifikasi RS256 dengan JWKS penyedia yang dikonfigurasi. */
class TokenIdentitas
{
    public function periksa(string $jwt, array $c, string $nonce): array
    {
        if ($jwt === '' || strlen($jwt) > 16384) {
            throw new GagalSso('Penyedia SSO tidak mengirim token identitas yang valid.', ['sebab' => 'id_token']);
        }
        try {
            $bagian = explode('.', $jwt);
            $kepala = count($bagian) === 3 ? JWT::jsonDecode(JWT::urlsafeB64Decode($bagian[0])) : null;
            if (! is_object($kepala) || ($kepala->alg ?? '') !== 'RS256'
                || ! is_string($kepala->kid ?? null) || $kepala->kid === '' || strlen($kepala->kid) > 200
                || ! empty($kepala->crit) || isset($kepala->b64)) {
                throw new GagalSso('Format token identitas tidak didukung.', ['sebab' => 'alg atau kid']);
            }
            /* Header belum dipercaya: kid hanya memilih kunci dari URL config; jku/x5u
               diabaikan. */
            $cache = 'sso.jwks.'.hash('sha256', $c['issuer'].'|'.$c['jwks']);
            $tersimpan = Cache::has($cache);
            $jwks = Cache::remember($cache, 300, fn () => $this->ambil($c));
            $kunci = $this->kunci($jwks, $kepala->kid);
            if (! $kunci && $tersimpan) {
                /* Rotasi kunci penyedia: ambil ulang sekali untuk kid yang baru. */
                $jwks = $this->ambil($c);
                Cache::put($cache, $jwks, 300);
                $kunci = $this->kunci($jwks, $kepala->kid);
            }
            if (! $kunci) {
                throw new GagalSso('Kunci penanda tangan token tidak dikenal.', ['sebab' => 'kid']);
            }
            $leeway = JWT::$leeway;
            $timestamp = JWT::$timestamp;
            try {
                JWT::$leeway = 60;
                /* Jam asli, tidak mengikuti tanggal demo Carbon. */
                JWT::$timestamp = time();
                $isi = (array) JWT::decode($jwt, JWK::parseKey($kunci, 'RS256'));
            } finally {
                JWT::$leeway = $leeway;
                JWT::$timestamp = $timestamp;
            }
        } catch (GagalSso $e) {
            throw $e;
        } catch (Throwable $e) {
            /* Token, kunci, dan rincian library tidak dimasukkan ke log/pesan user. */
            throw new GagalSso('Token identitas dari SSO tidak valid. Ulangi masuk.', ['sebab' => 'verifikasi token']);
        }

        if (! is_string($isi['iss'] ?? null) || $isi['iss'] !== $c['issuer']) {
            throw new GagalSso('Token identitas berasal dari penyedia yang berbeda.', ['sebab' => 'iss']);
        }
        $aud = $isi['aud'] ?? null;
        $aud = is_string($aud) ? [$aud] : $aud;
        if (! is_array($aud) || ! $aud || array_filter($aud, fn ($a) => ! is_string($a))
            || ! in_array($c['client_id'], $aud, true)) {
            throw new GagalSso('Token identitas bukan untuk aplikasi ini.', ['sebab' => 'aud']);
        }
        if ((count($aud) > 1 || array_key_exists('azp', $isi)) && ($isi['azp'] ?? null) !== $c['client_id']) {
            throw new GagalSso('Tujuan token identitas tidak cocok.', ['sebab' => 'azp']);
        }
        if (! is_int($isi['exp'] ?? null) || ! is_int($isi['iat'] ?? null)
            || $isi['exp'] <= $isi['iat'] || $isi['exp'] <= time() - 60 || $isi['iat'] > time() + 60) {
            throw new GagalSso('Waktu token identitas tidak valid. Ulangi masuk.', ['sebab' => 'exp atau iat']);
        }
        if (! is_string($isi['sub'] ?? null) || $isi['sub'] === '') {
            throw new GagalSso('Token identitas tidak membawa ID user.', ['sebab' => 'sub']);
        }
        if (! is_string($isi['nonce'] ?? null) || ! hash_equals($nonce, $isi['nonce'])) {
            throw new GagalSso('Token identitas tidak cocok dengan perjalanan masuk ini.', ['sebab' => 'nonce']);
        }

        return $isi;
    }

    private function ambil(array $c): array
    {
        try {
            $r = Http::acceptJson()->withoutRedirecting()->timeout(max(2, (int) $c['batas_detik']))->get($c['jwks']);
        } catch (ConnectionException $e) {
            throw new GagalSso('Kunci SSO belum dapat diambil. Coba lagi beberapa saat lagi.', ['sebab' => 'jwks tak terjangkau']);
        }
        $isi = $r->json();
        if (! $r->successful() || ! is_array($isi) || ! is_array($isi['keys'] ?? null)
            || ! $isi['keys'] || count($isi['keys']) > 100) {
            throw new GagalSso('Penyedia SSO mengirim kunci yang tidak valid.', ['sebab' => 'jwks', 'status' => $r->status()]);
        }

        return $isi;
    }

    private function kunci(array $jwks, string $kid): ?array
    {
        $cocok = array_values(array_filter($jwks['keys'], fn ($k) => is_array($k)
            && ($k['kid'] ?? null) === $kid && ($k['kty'] ?? '') === 'RSA'
            && ($k['alg'] ?? 'RS256') === 'RS256' && ($k['use'] ?? 'sig') === 'sig'
            && (! isset($k['key_ops']) || (is_array($k['key_ops']) && in_array('verify', $k['key_ops'], true)))
            && ! isset($k['d'])));

        return count($cocok) === 1 ? $cocok[0] : null;
    }
}
