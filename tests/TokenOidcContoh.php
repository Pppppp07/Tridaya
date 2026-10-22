<?php

namespace Tests;

use Firebase\JWT\JWT;

/** Kunci hanya dibuat di memori saat tes; tidak ada kredensial produksi dalam fixture. */
trait TokenOidcContoh
{
    private function pasanganOidc(): array
    {
        static $pasangan = null;
        if ($pasangan === null) {
            $opsi = ['config' => __DIR__.'/Fixtures/openssl.cnf', 'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA];
            $kunci = openssl_pkey_new($opsi);
            $this->assertNotFalse($kunci);
            $this->assertTrue(openssl_pkey_export($kunci, $pem, null, $opsi));
            $detail = openssl_pkey_get_details($kunci);
            $pasangan = ['pem' => $pem, 'rsa' => $detail['rsa']];
        }

        return $pasangan;
    }

    private function jwksOidc(string $kid = 'uji-1'): array
    {
        $rsa = $this->pasanganOidc()['rsa'];

        return ['keys' => [['kid' => $kid, 'kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256',
            'n' => JWT::urlsafeB64Encode($rsa['n']), 'e' => JWT::urlsafeB64Encode($rsa['e'])]]];
    }

    private function jwtOidc(array $klaim, string $kid = 'uji-1'): string
    {
        return JWT::encode(array_merge(['iss' => 'https://sso.contoh.test', 'aud' => 'simtlhp',
            'sub' => 'pegawai-1', 'iat' => time(), 'exp' => time() + 300, 'nonce' => 'nonce-uji'], $klaim),
            $this->pasanganOidc()['pem'], 'RS256', $kid);
    }
}
