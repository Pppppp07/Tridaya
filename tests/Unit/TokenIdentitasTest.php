<?php

namespace Tests\Unit;

use App\Support\Sso\GagalSso;
use App\Support\Sso\TokenIdentitas;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\TokenOidcContoh;

class TokenIdentitasTest extends TestCase
{
    use TokenOidcContoh;

    private array $konfig = ['issuer' => 'https://sso.contoh.test', 'jwks' => 'https://sso.contoh.test/jwks',
        'client_id' => 'simtlhp', 'batas_detik' => 2];

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush();
        Http::fake(['sso.contoh.test/jwks' => Http::response($this->jwksOidc())]);
    }

    public function test_tanda_tangan_sah_diterima_dan_jwks_disimpan_sementara(): void
    {
        $token = $this->jwtOidc(['nip' => '197801252005012005']);
        $validator = new TokenIdentitas;
        $this->assertSame('197801252005012005', $validator->periksa($token, $this->konfig, 'nonce-uji')['nip']);
        $validator->periksa($token, $this->konfig, 'nonce-uji');
        Http::assertSentCount(1);
    }

    public function test_perubahan_payload_tanpa_tanda_tangan_baru_ditolak(): void
    {
        $token = explode('.', $this->jwtOidc([]));
        $isi = json_decode(JWT::urlsafeB64Decode($token[1]), true);
        $isi['nip'] = 'identitas-palsu';
        $token[1] = JWT::urlsafeB64Encode(json_encode($isi));
        $this->expectException(GagalSso::class);
        (new TokenIdentitas)->periksa(implode('.', $token), $this->konfig, 'nonce-uji');
    }

    public static function klaimBuruk(): array
    {
        return [
            'issuer berbeda' => [['iss' => 'https://penyedia-lain.test']],
            'issuer hilang' => [['iss' => null]],
            'audience berbeda' => [['aud' => 'aplikasi-lain']],
            'audience rusak' => [['aud' => ['simtlhp', 1]]],
            'banyak audience tanpa azp' => [['aud' => ['simtlhp', 'lain']]],
            'azp berbeda' => [['azp' => 'lain']],
            'subject hilang' => [['sub' => null]],
            'subject kosong' => [['sub' => '']],
            'expiry hilang' => [['exp' => null]],
            'issued-at hilang' => [['iat' => null]],
            'expiry string' => [['exp' => '9999999999']],
            'expiry telah lewat' => [['exp' => 1]],
            'issued-at di masa depan' => [['iat' => 9999999998, 'exp' => 9999999999]],
            'nonce hilang' => [['nonce' => null]],
            'nonce berbeda' => [['nonce' => 'perjalanan-lain']],
        ];
    }

    #[DataProvider('klaimBuruk')]
    public function test_klaim_wajib_tidak_boleh_dilewati(array $klaim): void
    {
        $this->expectException(GagalSso::class);
        (new TokenIdentitas)->periksa($this->jwtOidc($klaim), $this->konfig, 'nonce-uji');
    }

    public function test_banyak_audience_dengan_azp_yang_cocok_diterima(): void
    {
        $isi = (new TokenIdentitas)->periksa($this->jwtOidc(['aud' => ['simtlhp', 'lain'], 'azp' => 'simtlhp']),
            $this->konfig, 'nonce-uji');
        $this->assertSame('simtlhp', $isi['azp']);
    }

    public function test_algoritma_hmac_tidak_bisa_memakai_kunci_publik_rsa(): void
    {
        $this->expectException(GagalSso::class);
        (new TokenIdentitas)->periksa(JWT::encode(['sub' => 'palsu'], str_repeat('x', 64), 'HS256', 'uji-1'),
            $this->konfig, 'nonce-uji');
    }

    public function test_kid_baru_memicu_satu_refresh_untuk_rotasi_kunci(): void
    {
        $validator = new TokenIdentitas;
        $validator->periksa($this->jwtOidc([]), $this->konfig, 'nonce-uji');
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['sso.contoh.test/jwks' => Http::response($this->jwksOidc('uji-2'))]);
        $this->assertSame('pegawai-1', $validator->periksa($this->jwtOidc([], 'uji-2'), $this->konfig, 'nonce-uji')['sub']);
        Http::assertSentCount(1);
    }

    public function test_jwks_rusak_atau_gagal_tidak_mengizinkan_login(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['sso.contoh.test/jwks' => Http::response(['keys' => []], 503)]);
        $this->expectException(GagalSso::class);
        (new TokenIdentitas)->periksa($this->jwtOidc([]), $this->konfig, 'nonce-uji');
    }
}
