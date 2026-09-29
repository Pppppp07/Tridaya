<?php

namespace Tests\Feature;

use App\Models\LogAktivitas;
use App\Models\User;
use App\Rules\LinkAman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Keamanan masuk, sesi, cache, dan cookie (27 Sep). Kata Hizkia: "tidak kalah
 * penting optimalisasi keamanan terutama pada sistem login, session, cache,
 * cookie".
 */
class KeamananMasukTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        RateLimiter::clear('masuk:'.sha1('setba@contoh.test|127.0.0.1'));
    }

    public function test_setiap_halaman_membawa_kepala_keamanan_dan_skrip_sebaris_bernonce(): void
    {
        $r = $this->get(route('masuk'))->assertOk();
        $csp = (string) $r->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self' 'nonce-", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertSame('DENY', $r->headers->get('X-Frame-Options'));
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('no-store', (string) $r->headers->get('Cache-Control'));

        /* Halaman berlogin: skrip sebaris kerangkanya membawa nonce yang sama
           dengan kepala CSP; skrip tanpa nonce tidak ada. */
        $this->masuk('setba@contoh.test');
        $r = $this->get(route('rekomendasi.index'))->assertOk();
        preg_match("/'nonce-([^']+)'/", (string) $r->headers->get('Content-Security-Policy'), $m);
        $this->assertNotEmpty($m[1] ?? null);
        $isi = $r->getContent();
        preg_match_all('/<script(?![^>]*\bsrc=)([^>]*)>/', $isi, $sebaris);
        $this->assertNotEmpty($sebaris[1]);
        foreach ($sebaris[1] as $atribut) {
            $this->assertStringContainsString('nonce="'.$m[1].'"', $atribut);
        }
        $this->assertStringContainsString('no-store', (string) $r->headers->get('Cache-Control'));
    }

    public function test_masuk_berhasil_tanpa_cookie_ingat_saya_dan_tercatat(): void
    {
        $r = $this->post('/masuk', ['email' => 'setba@contoh.test', 'password' => 'rahasia123'])
            ->assertRedirect(route('beranda'));
        $this->assertAuthenticated();
        foreach ($r->headers->getCookies() as $c) {
            $this->assertStringStartsNotWith('remember_web', $c->getName(), 'Tidak boleh ada cookie ingat-saya yang dipaksakan.');
        }

        $u = User::where('email', 'setba@contoh.test')->first();
        $this->assertNotNull($u->terakhir_masuk_pada);
        $log = LogAktivitas::where('aksi', 'masuk')->latest('id')->first();
        $this->assertSame($u->id, $log->user_id);
        $this->assertSame('setba', $log->peran);
        $this->assertSame('127.0.0.1', $log->ip);

        $this->post(route('keluar'))->assertRedirect(route('masuk'));
        $this->assertGuest();
        $this->assertTrue(LogAktivitas::where('aksi', 'keluar')->where('user_id', $u->id)->exists());
    }

    public function test_percobaan_salah_dibatasi_dan_tercatat_tanpa_password(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/masuk', ['email' => 'setba@contoh.test', 'password' => 'salah-'.$i])
                ->assertSessionHasErrors(['email' => 'Email atau password salah.']);
        }
        /* Keenam kali — walau password-nya benar — ditahan dulu. */
        $this->post('/masuk', ['email' => 'setba@contoh.test', 'password' => 'rahasia123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString('Terlalu banyak percobaan', session('errors')->first('email'));

        $this->assertSame(5, LogAktivitas::where('aksi', 'masuk.gagal')->count());
        $this->assertSame(1, LogAktivitas::where('aksi', 'masuk.dikunci')->count());
        foreach (LogAktivitas::where('aksi', 'like', 'masuk.%')->get() as $x) {
            $this->assertStringNotContainsString('salah-', json_encode($x->rincian).$x->ringkasan, 'Password tidak boleh masuk log.');
            $this->assertNull($x->user_id);
        }
    }

    /**
     * Halaman masuk sungguhan bersih seperti saat dipasang; akun contoh, email,
     * dan password selama pengembangan pindah ke Login developer (28 Sep).
     * Keduanya padam sendiri di produksi.
     */
    public function test_akun_contoh_dan_formulir_password_bisa_dipadamkan_untuk_produksi(): void
    {
        config(['simtlhp.sso.driver' => 'simulasi']);
        $this->get(route('masuk'))->assertOk()
            ->assertDontSee('Akun contoh')->assertDontSee('rahasia123')->assertDontSee('data-form-masuk', false)
            ->assertSee('Masuk dengan SSO PU')
            ->assertSee('href="'.route('masuk.pengembang').'"', false)->assertSee('Login developer');
        $this->get(route('masuk.pengembang'))->assertOk()
            ->assertSee('Login developer')->assertSee('Akun contoh')->assertSee('rahasia123')
            ->assertSee('data-form-masuk', false)->assertSee('href="'.route('masuk').'"', false);

        /* Produksi yang masih memakai password sebagai cadangan: formulirnya
           di halaman masuk sungguhan, tanpa akun contoh dan tanpa Login developer. */
        config(['simtlhp.akun_demo' => false]);
        $this->get(route('masuk'))->assertOk()->assertSee('data-form-masuk', false)
            ->assertDontSee('Akun contoh')->assertDontSee('rahasia123')->assertDontSee('Login developer');
        $this->get(route('masuk.pengembang'))->assertNotFound();

        /* SSO saja: formulir password hilang, kirimannya ditolak. */
        config(['simtlhp.masuk.password' => false, 'simtlhp.akun_demo' => true]);
        $this->get(route('masuk'))->assertOk()->assertDontSee('Password')->assertSee('Masuk dengan')
            ->assertDontSee('Login developer');
        $this->get(route('masuk.pengembang'))->assertNotFound();
        $this->post('/masuk', ['email' => 'setba@contoh.test', 'password' => 'rahasia123'])->assertNotFound();
        $this->assertGuest();
    }

    /**
     * Halaman masuk yang ditata ulang (28 Sep): akun contoh dikelompokkan,
     * isian password punya mata dan peringatan Caps Lock, latarnya hiasan
     * yang dilewati pembaca layar — dan skrip sebarisnya (daftar akun contoh
     * yang terlipat) tetap membawa nonce CSP.
     */
    public function test_halaman_masuk_tertata_dan_skrip_sebarisnya_bernonce(): void
    {
        /* Halaman masuk sungguhan: hanya skrip gerak tiba di kerangka. */
        $r = $this->get(route('masuk'))->assertOk()
            ->assertSee('<div class="msk-latar" aria-hidden="true">', false);
        preg_match("/'nonce-([^']+)'/", (string) $r->headers->get('Content-Security-Policy'), $m);
        preg_match_all('/<script(?![^>]*\bsrc=)([^>]*)>/', $r->getContent(), $sebaris);
        $this->assertCount(1, $sebaris[1]);
        $this->assertStringContainsString('nonce="'.$m[1].'"', $sebaris[1][0]);

        /* Login developer: akun contoh, mata password, Caps Lock. */
        $r = $this->get(route('masuk.pengembang'))->assertOk()
            ->assertSee('Petugas pusat')->assertSee('Penanggung jawab unit kerja')
            ->assertSee('data-mata-password', false)->assertSee('Caps Lock aktif')
            ->assertSee('<div class="msk-latar" aria-hidden="true">', false);
        preg_match("/'nonce-([^']+)'/", (string) $r->headers->get('Content-Security-Policy'), $m);
        $this->assertNotEmpty($m[1] ?? null);
        $isi = $r->getContent();
        preg_match_all('/<script(?![^>]*\bsrc=)([^>]*)>/', $isi, $sebaris);
        $this->assertCount(2, $sebaris[1], 'Gerak tiba di kerangka dan lipatan akun contoh.');
        foreach ($sebaris[1] as $atribut) {
            $this->assertStringContainsString('nonce="'.$m[1].'"', $atribut);
        }

        /* Petugas pusat lebih dulu, urut peran; semua akun aktif ada. */
        $this->assertLessThan(strpos($isi, 'data-email="uki@contoh.test"'), strpos($isi, 'data-email="setba@contoh.test"'));
        $this->assertLessThan(strpos($isi, 'data-email="admin@contoh.test"'), strpos($isi, 'data-email="dti@contoh.test"'));
        $this->assertLessThan(strpos($isi, 'Penanggung jawab unit kerja'), strpos($isi, 'data-email="admin@contoh.test"'));
        $this->assertSame(User::where('aktif', true)->count(), substr_count($isi, 'data-email="'));

        /* Sesudah gagal: kembali ke Login developer, email-nya tetap, kata
           password-nya yang difokuskan, dan kedua isian ditandai salah. */
        $this->from(route('masuk.pengembang'))->post('/masuk', ['email' => 'setba@contoh.test', 'password' => 'salah'])
            ->assertRedirect(route('masuk.pengembang'));
        $isi = $this->get(route('masuk.pengembang'))->assertSee('Email atau password salah.')->getContent();
        $this->assertMatchesRegularExpression('/id="password"[^>]*autofocus/', $isi);
        $this->assertDoesNotMatchRegularExpression('/id="email"[^>]*autofocus/', $isi);
        $this->assertStringContainsString('value="setba@contoh.test"', $isi);
        $this->assertSame(2, substr_count($isi, 'aria-invalid="true"'));
    }

    public function test_link_berkas_harus_alamat_web(): void
    {
        $this->assertTrue(LinkAman::sah('https://drive.pu.go.id/berkas/1'));
        $this->assertTrue(LinkAman::sah('http://intranet.pu.go.id/a?b=1'));
        foreach (['javascript:alert(1)', 'JAVASCRIPT:alert(1)', 'data:text/html,<b>x</b>', 'vbscript:x', '//contoh.test/a',
            'drive.pu.go.id/berkas', "https://a.test/\njavascript:x", 'https:///tanpa-host'] as $buruk) {
            $this->assertFalse(LinkAman::sah($buruk), $buruk);
        }

        /* Link lama yang tidak sah tetap terbaca namanya, tapi tidak pernah
           jadi href. */
        $html = (string) $this->blade('<x-berkas nama="Bukti" link="javascript:alert(1)" />');
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertStringContainsString('href="#"', $html);
    }
}
