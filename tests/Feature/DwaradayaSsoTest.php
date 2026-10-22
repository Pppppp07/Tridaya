<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna as P;
use App\Models\LogAktivitas;
use App\Models\User;
use App\Support\Sso\TokenDwaradaya;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DwaradayaSsoTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'khusus-pengujian-dwaradaya-bukan-secret-server-0123456789-abcdefghijk';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'simtlhp.sso.driver' => 'dwaradaya',
            'simtlhp.sso.dwaradaya.secret' => self::SECRET,
            'simtlhp.sso.dwaradaya.algoritma' => 'HS256',
            'simtlhp.sso.dwaradaya.portal' => 'https://superapp.contoh.test/portal',
            'simtlhp.sso.dwaradaya.logout' => null,
            'simtlhp.sso.dwaradaya.issuer' => null,
            'simtlhp.sso.dwaradaya.audience' => null,
            'simtlhp.sso.dwaradaya.sekali_pakai' => false,
            'simtlhp.sso.dwaradaya.cek_per_menit' => 600,
        ]);
    }

    private function pegawai(array $attrs = []): User
    {
        return User::factory()->create($attrs + [
            'nip' => '198001012005011001', 'peran' => P::UKI, 'aktif' => true,
        ]);
    }

    private function jwt(array $isi = [], ?string $secret = null, string $alg = 'HS256'): string
    {
        return JWT::encode($isi + ['user' => '198001012005011001', 'exp' => time() + 300], $secret ?? self::SECRET, $alg);
    }

    private function cek(string $token)
    {
        // SuperApp tidak wajib mengirim Accept: application/json.
        return $this->withHeader('Authorization', 'Bearer '.$token)->post('/api/sso-check');
    }

    public function test_cek_superapp_stateless_dan_format_json_persis(): void
    {
        $u = $this->pegawai();
        $res = $this->cek($this->jwt())
            ->assertOk()->assertExactJson(['success' => true, 'exists' => true])
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
        $this->assertEmpty($res->headers->getCookies());
        $this->assertGuest();
        $this->assertNull($u->fresh()->terakhir_masuk_pada);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('log_aktivitas', ['aksi' => 'masuk']);
    }

    public function test_fallback_query_dan_bearer_masuk_keduanya_diterima(): void
    {
        $u = $this->pegawai();
        $this->post('/api/sso-check?token='.$this->jwt())->assertOk()->assertExactJson(['success' => true, 'exists' => true]);
        $this->withHeader('Authorization', 'Bearer '.$this->jwt())->get('/sso-login')->assertRedirect(route('beranda'));
        $this->assertAuthenticatedAs($u);
    }

    public function test_klik_aplikasi_masuk_memakai_izin_lokal_dan_membuang_konteks_lama(): void
    {
        $u = $this->pegawai();
        $u->forceFill(['peran_tambahan' => ['inspektorat'], 'pengaturan' => ['tema' => 'gelap']])->save();
        $token = $this->jwt(['role' => 'admin', 'satker_id' => 123, 'name' => 'Nama palsu']);
        $this->withSession(['akses_aktif' => ['peran' => 'admin'], 'filter_satker' => 123, 'url.intended' => 'https://asing.test/'])
            ->get('/sso-login?token='.$token)->assertRedirect(route('beranda'))
            ->assertSessionMissing('akses_aktif')->assertSessionMissing('filter_satker')
            ->assertSessionMissing('url.intended')->assertSessionHas('masuk_lewat_sso', true)
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertAuthenticatedAs($u);
        $this->assertSame(P::UKI, $u->fresh()->peran);
        $this->assertSame(['inspektorat'], $u->fresh()->peran_tambahan);
        $this->assertSame('gelap', $u->fresh()->setelan('tema'));
        $this->assertNotSame('Nama palsu', $u->fresh()->name);
        $this->assertNotNull($u->fresh()->terakhir_masuk_pada);
        $this->assertDatabaseHas('log_aktivitas', ['aksi' => 'masuk', 'user_id' => $u->id]);
        $this->assertStringNotContainsString($token, json_encode(session()->all()));
        $this->assertStringNotContainsString($token, LogAktivitas::all()->toJson());
    }

    public function test_akun_tidak_ada_atau_nonaktif_ditolak_tanpa_registrasi_otomatis(): void
    {
        $this->cek($this->jwt())->assertUnauthorized()->assertJsonStructure(['error'])->assertJsonCount(1);
        $this->assertDatabaseCount('users', 0);
        $this->pegawai(['aktif' => false]);
        $this->cek($this->jwt())->assertUnauthorized()->assertJsonCount(1);
        $this->get('/sso-login?token='.$this->jwt())->assertRedirect(route('sso.dwaradaya.hasil'));
        $this->get(route('sso.dwaradaya.hasil'))->assertForbidden()->assertSee('Tidak memiliki akses');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_nip_adalah_utama_dan_tidak_jatuh_ke_email_akun_lain(): void
    {
        $u = $this->pegawai(['email' => 'admin@contoh.test']);
        $this->cek($this->jwt(['user' => '199001012010011001', 'email' => $u->email]))->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_email_untuk_non_nip_harus_sudah_diizinkan(): void
    {
        $u = $this->pegawai(['nip' => null, 'email' => 'tamu@contoh.test']);
        $this->cek($this->jwt(['user' => '', 'email' => 'TAMU@contoh.test']))->assertOk();
        $this->flushHeaders();
        $this->get('/sso-login?token='.$this->jwt(['user' => $u->email]))->assertRedirect(route('beranda'));
        $this->assertAuthenticatedAs($u);
    }

    public function test_identitas_email_ambigu_ditolak(): void
    {
        $this->pegawai(['nip' => null, 'email' => 'tamu@contoh.test']);
        $this->pegawai(['nip' => null, 'email' => 'TAMU@contoh.test']);
        $this->cek($this->jwt(['user' => '', 'email' => 'tamu@contoh.test']))->assertUnauthorized();
    }

    public static function klaimRusak(): array
    {
        return [
            'exp kosong' => [['exp' => null]],
            'exp teks' => [['exp' => '9999999999']],
            'exp lampau' => [['exp' => 1]],
            'iat masa depan' => [['iat' => 9999999999]],
            'nbf masa depan' => [['nbf' => 9999999999]],
            'user angka' => [['user' => 198001012005011001]],
            'user larik' => [['user' => ['nip' => '198001012005011001']]],
            'nip bercampur huruf' => [['user' => 'x198001012005011001']],
            'email beda dari user' => [['user' => 'a@contoh.test', 'email' => 'b@contoh.test']],
            'tanpa identitas' => [['user' => '', 'email' => '']],
            'email rusak' => [['email' => 'bukan-email']],
        ];
    }

    #[DataProvider('klaimRusak')]
    public function test_token_dengan_klaim_tidak_valid_ditolak(array $isi): void
    {
        $this->pegawai();
        $this->cek($this->jwt($isi))->assertUnauthorized()->assertJsonCount(1)->assertJsonStructure(['error']);
        $this->assertGuest();
    }

    public function test_signature_algoritma_dan_sumber_token_diverifikasi(): void
    {
        $this->pegawai();
        foreach ([$this->jwt(secret: str_repeat('x', 64)), $this->jwt(alg: 'HS512'), 'e30.e30.palsu', str_repeat('a', 16385)] as $jwt) {
            $this->cek($jwt)->assertUnauthorized()->assertJsonCount(1);
        }
        $this->withHeader('Authorization', 'Bearer '.$this->jwt())
            ->post('/api/sso-check?token=berbeda')->assertUnauthorized();
        $this->flushHeaders();
        $this->post('/api/sso-check?token[]=abc')->assertUnauthorized()->assertJsonCount(1);
        $this->post('/api/sso-check', ['token' => $this->jwt()])->assertUnauthorized();
        $this->get('/sso-login?token[]=abc')->assertRedirect(route('sso.dwaradaya.hasil'));
    }

    public function test_issuer_audience_opsional_diperiksa_bila_dipasang(): void
    {
        $this->pegawai();
        config(['simtlhp.sso.dwaradaya.issuer' => 'dwaradaya', 'simtlhp.sso.dwaradaya.audience' => 'rekala']);
        $this->cek($this->jwt())->assertUnauthorized();
        $this->cek($this->jwt(['iss' => 'dwaradaya', 'aud' => ['aplikasi-lain', 'rekala']]))->assertOk();
        $this->cek($this->jwt(['iss' => 'dwaradaya', 'aud' => 'lain']))->assertUnauthorized();
    }

    public function test_token_rusak_tidak_mengakhiri_sesi_aktif_dan_tidak_bocor(): void
    {
        $u = $this->pegawai();
        $token = $this->jwt(secret: str_repeat('x', 64));
        $this->actingAs($u)->get('/sso-login?token='.$token)->assertRedirect(route('sso.dwaradaya.hasil'))
            ->assertDontSee($token)->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertAuthenticatedAs($u);
        $this->get(route('sso.dwaradaya.hasil'))->assertForbidden()->assertSee('Gagal masuk')->assertDontSee($token);
        $this->assertStringNotContainsString($token, json_encode(session()->all()));
        $this->assertStringNotContainsString($token, LogAktivitas::all()->toJson());
    }

    public function test_pergantian_akun_perlu_konfirmasi_dan_izin_dicek_ulang(): void
    {
        $u = $this->pegawai();
        $lama = $this->pegawai(['nip' => '199001012010011001', 'peran' => P::PIMPINAN]);
        $jwt = $this->jwt();
        $this->actingAs($lama)->get('/sso-login?token='.$jwt)->assertRedirect(route('sso.dwaradaya.konfirmasi'));
        $this->assertAuthenticatedAs($lama);
        $this->get(route('sso.dwaradaya.konfirmasi'))->assertOk()->assertSee($u->name)->assertDontSee($jwt);
        $this->assertStringNotContainsString($jwt, json_encode(session()->all()));
        $u->update(['aktif' => false]);
        $this->post(route('sso.dwaradaya.lanjutkan'))->assertRedirect(route('sso.dwaradaya.hasil'));
        $this->assertAuthenticatedAs($lama);
        $this->assertFalse(session()->has('dwaradaya.konfirmasi'));
    }

    public function test_konfirmasi_mengganti_akun_dan_bisa_dibatalkan(): void
    {
        $u = $this->pegawai();
        $lama = $this->pegawai(['nip' => '199001012010011001', 'peran' => P::PIMPINAN]);
        $this->actingAs($lama)->get('/sso-login?token='.$this->jwt())->assertRedirect(route('sso.dwaradaya.konfirmasi'));
        $this->post(route('sso.dwaradaya.lanjutkan'), ['pilihan' => 'batal'])->assertRedirect(route('beranda'));
        $this->assertAuthenticatedAs($lama);
        $this->assertFalse(session()->has('dwaradaya.konfirmasi'));
        $this->get('/sso-login?token='.$this->jwt())->assertRedirect(route('sso.dwaradaya.konfirmasi'));
        $this->post(route('sso.dwaradaya.lanjutkan'))->assertRedirect(route('beranda'));
        $this->assertAuthenticatedAs($u);
    }

    public function test_sesi_lama_yang_berakhir_atau_dicabut_tidak_menghalangi_identitas_baru(): void
    {
        $u = $this->pegawai();
        $lama = $this->pegawai(['nip' => '199001012010011001', 'aktif' => false]);
        $this->actingAs($lama)->get('/sso-login?token='.$this->jwt())->assertRedirect(route('sso.dwaradaya.konfirmasi'));
        $this->get(route('sso.dwaradaya.konfirmasi'))->assertOk()->assertDontSee('data-kontrol-nav');
        $this->post(route('sso.dwaradaya.lanjutkan'))->assertRedirect(route('beranda'));
        $this->assertAuthenticatedAs($u);
    }

    public function test_mode_sekali_pakai_tidak_dikonsumsi_oleh_cek_superapp(): void
    {
        $this->pegawai();
        config(['simtlhp.sso.dwaradaya.sekali_pakai' => true]);
        $jwt = $this->jwt();
        $this->cek($jwt)->assertOk();
        $this->cek($jwt)->assertOk();
        $this->flushHeaders();
        $this->get('/sso-login?token='.$jwt)->assertRedirect(route('beranda'));
        Auth::logout();
        session()->invalidate();
        $this->get('/sso-login?token='.$jwt)->assertRedirect(route('sso.dwaradaya.hasil'));
        $this->assertGuest();
    }

    public function test_konfirmasi_kedaluwarsa_tidak_mengganti_sesi_dan_mode_bawaan_mengikuti_exp(): void
    {
        $u = $this->pegawai();
        $lama = $this->pegawai(['nip' => '199001012010011001']);
        $jwt = $this->jwt();
        $this->actingAs($lama)->get('/sso-login?token='.$jwt)->assertRedirect(route('sso.dwaradaya.konfirmasi'));
        session()->put('dwaradaya.konfirmasi.exp', time() - 1);
        $this->post(route('sso.dwaradaya.lanjutkan'))->assertRedirect(route('sso.dwaradaya.hasil'));
        $this->assertAuthenticatedAs($lama);
        Auth::logout();
        session()->invalidate();
        $this->get('/sso-login?token='.$jwt)->assertRedirect(route('beranda'));
        $this->assertAuthenticatedAs($u);
        $sesiLama = session()->getId();
        $this->get('/sso-login?token='.$jwt)->assertRedirect(route('beranda'));
        $this->assertAuthenticatedAs($u);
        $this->assertNotSame($sesiLama, session()->getId());
    }

    public function test_validasi_exp_memakai_jam_nyata_bukan_tanggal_demo(): void
    {
        $this->pegawai();
        try {
            Carbon::setTestNow('2050-01-01 00:00:00');
            $this->cek($this->jwt())->assertOk();
            $this->cek($this->jwt(['exp' => time() - 60]))->assertUnauthorized();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_hal_gagal_di_api_tetap_json_murni_termasuk_throttle(): void
    {
        $this->get('/api/sso-check')->assertStatus(405)->assertJsonCount(1)->assertJsonStructure(['error']);
        config(['simtlhp.sso.dwaradaya.cek_per_menit' => 1]);
        $this->post('/api/sso-check')->assertUnauthorized();
        $this->post('/api/sso-check')->assertStatus(429)->assertJsonCount(1)->assertJsonStructure(['error']);
        Cache::flush();
        $this->mock(TokenDwaradaya::class)->shouldReceive('dariPermintaan')->andThrow(new \RuntimeException('rahasia-yang-tidak-boleh-bocor'));
        $this->post('/api/sso-check')->assertStatus(500)->assertJsonCount(1)->assertDontSee('rahasia-yang-tidak-boleh-bocor');
    }

    public function test_konfigurasi_kosong_dan_driver_mati_tidak_menerima_login(): void
    {
        $this->pegawai();
        config(['simtlhp.sso.dwaradaya.secret' => '']);
        $this->cek($this->jwt())->assertUnauthorized()->assertJsonCount(1);
        config(['simtlhp.sso.driver' => 'mati']);
        $this->post('/api/sso-check')->assertNotFound()->assertJsonCount(1);
        $this->get('/sso-login?token='.$this->jwt())->assertNotFound()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertGuest();
    }

    public function test_portal_dan_logout_lokal_tidak_menebak_endpoint(): void
    {
        $this->get('/masuk')->assertOk()->assertSee('Login ke Dwaradaya')->assertSee('Masuk dengan Dwaradaya');
        $this->get('/masuk/sso')->assertRedirect('https://superapp.contoh.test/portal');
        config(['simtlhp.sso.dwaradaya.portal' => 'http://tidak-aman.test']);
        $this->get('/masuk/sso')->assertForbidden();
        $u = $this->pegawai();
        $this->actingAs($u)->withSession(['masuk_lewat_sso' => true])->post('/keluar')->assertRedirect(route('masuk'));
        $this->assertGuest();
    }

    public function test_https_wajib_di_produksi_dan_post_konfirmasi_memerlukan_csrf(): void
    {
        $this->pegawai();
        $this->app->instance('env', 'production');
        $this->cek($this->jwt())->assertUnauthorized()->assertJsonCount(1);
        $this->post('https://localhost:8000/api/sso-check')->assertOk();
        $this->flushHeaders();
        $this->post('/masuk/dwaradaya/konfirmasi')->assertRedirect(route('masuk'));
        $this->assertGuest();
    }
}
