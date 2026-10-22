<?php

namespace App\Support\Sso;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Throwable;

/** JWT SuperApp: kunci dan algoritma selalu dari konfigurasi server, bukan header token. */
class TokenDwaradaya
{
    public function dariPermintaan(Request $r): array
    {
        $header = $r->header('Authorization');
        $query = $r->query('token');
        $token = null;
        if ($header !== null) {
            if (! is_string($header) || ! preg_match('/^Bearer\s+(\S+)$/iD', $header, $m)) {
                throw new GagalSso('Token Dwaradaya tidak valid. Buka kembali infaX dari Dwaradaya.', ['sebab' => 'header token']);
            }
            $token = $m[1];
        }
        if ($query !== null) {
            if (! is_string($query) || ($token !== null && ! hash_equals($token, $query))) {
                throw new GagalSso('Token Dwaradaya tidak valid. Buka kembali infaX dari Dwaradaya.', ['sebab' => 'token ambigu']);
            }
            $token ??= $query;
        }
        if (! is_string($token) || $token === '' || strlen($token) > 16384) {
            throw new GagalSso('Token Dwaradaya tidak tersedia atau tidak valid. Buka infaX dari Dwaradaya.', ['sebab' => 'tanpa token']);
        }

        $isi = $this->periksa($token);

        return ['identitas' => $this->identitas($isi), 'exp' => $isi['exp'], 'sidik' => hash('sha256', $token)];
    }

    public function periksa(string $token): array
    {
        $c = (array) config('simtlhp.sso.dwaradaya');
        $alg = $c['algoritma'] ?? 'HS256';
        $minimum = ['HS256' => 32, 'HS384' => 48, 'HS512' => 64];
        $secret = $c['secret'] ?? null;
        if (! is_string($alg) || ! isset($minimum[$alg]) || ! is_string($secret) || strlen($secret) < $minimum[$alg]) {
            throw new GagalSso('Koneksi Dwaradaya belum dikonfigurasi lengkap. Hubungi Admin DTI.', ['sebab' => 'konfigurasi dwaradaya']);
        }
        if (strlen($token) > 16384 || ! preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/D', $token)) {
            throw new GagalSso('Token Dwaradaya tidak valid. Buka kembali infaX dari Dwaradaya.', ['sebab' => 'format token']);
        }
        try {
            $header = JWT::jsonDecode(JWT::urlsafeB64Decode(explode('.', $token)[0]));
            if (! is_object($header) || ($header->alg ?? null) !== $alg || ! empty($header->crit) || isset($header->b64)) {
                throw new GagalSso('Format token Dwaradaya tidak didukung.', ['sebab' => 'algoritma token']);
            }
            $leeway = JWT::$leeway;
            $timestamp = JWT::$timestamp;
            try {
                // Kedaluwarsa memakai jam nyata, termasuk saat tanggal demo aplikasi diaktifkan.
                JWT::$leeway = 0;
                JWT::$timestamp = time();
                $isi = (array) JWT::decode($token, new Key($secret, $alg));
            } finally {
                JWT::$leeway = $leeway;
                JWT::$timestamp = $timestamp;
            }
        } catch (GagalSso $e) {
            throw $e;
        } catch (Throwable $e) {
            // Tidak membawa token, secret atau rincian library ke exception/log.
            throw new GagalSso('Token Dwaradaya tidak valid atau sudah kedaluwarsa. Buka kembali infaX dari Dwaradaya.', ['sebab' => 'verifikasi token']);
        }
        if (! is_int($isi['exp'] ?? null) || $isi['exp'] <= time()
            || (isset($isi['iat']) && (! is_int($isi['iat']) || $isi['iat'] > time()))
            || (isset($isi['nbf']) && (! is_int($isi['nbf']) || $isi['nbf'] > time()))) {
            throw new GagalSso('Waktu token Dwaradaya tidak valid.', ['sebab' => 'waktu token']);
        }
        // Dokumen tidak mewajibkan iss/aud. Bila penyedia menyediakannya, pin nilai resminya.
        if (! empty($c['issuer']) && ($isi['iss'] ?? null) !== $c['issuer']) {
            throw new GagalSso('Penyedia token Dwaradaya tidak cocok.', ['sebab' => 'issuer']);
        }
        if (! empty($c['audience'])) {
            $aud = $isi['aud'] ?? null;
            $aud = is_string($aud) ? [$aud] : $aud;
            if (! is_array($aud) || array_filter($aud, fn ($v) => ! is_string($v)) || ! in_array($c['audience'], $aud, true)) {
                throw new GagalSso('Token Dwaradaya bukan untuk aplikasi ini.', ['sebab' => 'audience']);
            }
        }

        return $isi;
    }

    private function identitas(array $isi): Identitas
    {
        foreach (['user', 'email'] as $k) {
            if (isset($isi[$k]) && ! is_string($isi[$k])) {
                throw new GagalSso('Identitas dari Dwaradaya tidak valid.', ['sebab' => 'jenis identitas']);
            }
        }
        $user = trim($isi['user'] ?? '');
        $email = mb_strtolower(trim($isi['email'] ?? ''));
        if ($email !== '' && (strlen($email) > 254 || ! filter_var($email, FILTER_VALIDATE_EMAIL))) {
            throw new GagalSso('Email dari Dwaradaya tidak valid.', ['sebab' => 'email']);
        }
        // NIP hanya dirapikan spasinya; huruf atau identitas lain tidak diubah menjadi NIP.
        $nip = preg_replace('/\s+/', '', $user);
        if (preg_match('/^\d{18}$/D', $nip)) {
            return new Identitas(nip: $nip, email: $email);
        }
        // Akun non-NIP boleh memakai email yang sudah diizinkan Admin. NIP tak dikenal
        // tidak jatuh ke email, sehingga token NIP A tidak bisa membuka akun B.
        if ($user !== '') {
            $user = mb_strtolower($user);
            if (strlen($user) > 254 || ! filter_var($user, FILTER_VALIDATE_EMAIL)
                || ($email !== '' && $email !== $user)) {
                throw new GagalSso('Identitas dari Dwaradaya tidak valid.', ['sebab' => 'identitas user']);
            }
            $email = $user;
        }
        if ($email === '') {
            throw new GagalSso('Dwaradaya tidak mengirim NIP atau email yang valid.', ['sebab' => 'tanpa identitas']);
        }

        return new Identitas(nip: '', email: $email);
    }
}
