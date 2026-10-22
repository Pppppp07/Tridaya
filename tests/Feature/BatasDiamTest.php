<?php

namespace Tests\Feature;

use App\Models\LogAktivitas;
use App\Models\Rekomendasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/** Keluar otomatis sesudah 10 menit tanpa aktivitas. */
class BatasDiamTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        config(['simtlhp.sesi.batas_diam' => 10]);
    }

    public function test_masih_dalam_batas_tetap_masuk(): void
    {
        $this->masuk('setba@contoh.test')->get(route('rekomendasi.index'))->assertOk();
        $this->travel(9)->minutes();
        $this->get(route('rekomendasi.index'))->assertOk();
        $this->travel(9)->minutes();
        $this->get(route('rekomendasi.index'))->assertOk();
        $this->assertAuthenticated();
    }

    public function test_lewat_batas_keluar_otomatis_dengan_pesan_dan_tercatat(): void
    {
        $r = Rekomendasi::firstOrFail();
        $this->masuk('setba@contoh.test')->get(route('rekomendasi.index'))->assertOk();
        $this->travel(10)->minutes();

        $this->get(route('rekomendasi.show', $r))->assertRedirect(route('masuk'))
            ->assertSessionHas('sesi_berakhir')
            ->assertSessionHas('url.intended', route('rekomendasi.show', $r));
        $this->assertGuest();
        $this->get(route('masuk'))->assertOk()->assertSee('Sesi berakhir karena 10 menit tanpa aktivitas');

        $log = LogAktivitas::where('aksi', 'keluar')->latest('id')->firstOrFail();
        $this->assertSame('otomatis', $log->rincian['cara']);
        $this->assertSame('Keluar otomatis: 10 menit tanpa aktivitas', $log->ringkasan);

        $this->masuk('setba@contoh.test')->get(route('akun', ['tab' => 'keamanan']))
            ->assertSee('Keluar otomatis — 10 menit tanpa aktivitas');
    }

    /** Sesi habis lalu orang lain masuk di peramban yang sama: ke beranda, bukan halaman orang sebelumnya. */
    public function test_orang_lain_yang_masuk_sesudah_sesi_habis_ke_beranda(): void
    {
        $this->masuk('admin@contoh.test')->get(route('master'))->assertOk();
        $this->travel(10)->minutes();
        $this->get(route('master', ['tab' => 'unit']))->assertRedirect(route('masuk'))
            ->assertSessionHas('url.intended_oleh', $this->akun('admin@contoh.test')->id);

        $this->post('/masuk', ['email' => 'setba@contoh.test', 'password' => 'rahasia123'])->assertRedirect(route('beranda'));
        $this->assertSame('setba@contoh.test', auth()->user()->email);
        $this->assertNull(session('url.intended_oleh'));
    }

    public function test_orang_yang_sama_kembali_ke_halaman_terakhirnya(): void
    {
        $r = Rekomendasi::firstOrFail();
        $this->masuk('setba@contoh.test')->get(route('rekomendasi.index'))->assertOk();
        $this->travel(10)->minutes();
        $this->get(route('rekomendasi.show', $r))->assertRedirect(route('masuk'));

        $this->post('/masuk', ['email' => 'setba@contoh.test', 'password' => 'rahasia123'])
            ->assertRedirect(route('rekomendasi.show', $r));
    }

    public function test_pesan_tidak_habis_oleh_cek_lonceng_di_latar(): void
    {
        $this->masuk('setba@contoh.test')->get(route('rekomendasi.index'))->assertOk();
        $this->travel(11)->minutes();
        $this->getJson(route('pemberitahuan.ringkas'))->assertStatus(401);
        $this->getJson(route('pemberitahuan.ringkas'))->assertStatus(401);

        $this->get(route('masuk'))->assertOk()->assertSee('Sesi berakhir karena 10 menit tanpa aktivitas');
        $this->get(route('masuk'))->assertOk()->assertDontSee('Sesi berakhir karena');
    }

    public function test_cek_lonceng_dan_cek_sisa_tidak_memperpanjang(): void
    {
        $this->masuk('setba@contoh.test')->get(route('rekomendasi.index'))->assertOk();
        $this->travel(5)->minutes();
        $this->getJson(route('pemberitahuan.ringkas'))->assertOk();
        $this->travel(4)->minutes();
        $this->getJson(route('sesi.sisa'))->assertOk()->assertExactJson(['sisa' => 60, 'batas' => 600]);
        $this->travel(1)->minutes();

        $this->getJson(route('pemberitahuan.ringkas'))->assertStatus(401)->assertJson(['sesi' => 'berakhir']);
        $this->assertGuest();
    }

    public function test_tetap_masuk_memperpanjang(): void
    {
        $this->masuk('setba@contoh.test')->get(route('rekomendasi.index'))->assertOk();
        $this->travel(9)->minutes();
        $this->postJson(route('sesi.lanjut'))->assertOk()->assertJson(['sisa' => 600]);
        $this->travel(9)->minutes();
        $this->get(route('rekomendasi.index'))->assertOk();
        $this->assertAuthenticated();
    }

    public function test_akun_hanya_melihat_tetap_bisa_memperpanjang(): void
    {
        $this->masuk('pimpinan@contoh.test')->postJson(route('sesi.lanjut'))->assertOk();
    }

    public function test_halaman_berakhir_hanya_menerima_alamat_di_aplikasi_ini(): void
    {
        $this->get(route('sesi.berakhir', ['kembali' => '/rekomendasi/5?tab=a']))->assertRedirect(route('masuk'))
            ->assertSessionHas('sesi_berakhir')
            ->assertSessionHas('url.intended', url('/rekomendasi/5?tab=a'));

        foreach (['//jahat.test/x', 'https://jahat.test', '/\\jahat.test', '/masuk', '/keluar'] as $kembali) {
            $this->flushSession();
            $this->get(route('sesi.berakhir', ['kembali' => $kembali]))->assertRedirect(route('masuk'))
                ->assertSessionMissing('url.intended');
        }
    }

    public function test_halaman_berlogin_memuat_peringatan(): void
    {
        $this->masuk('setba@contoh.test')->get(route('rekomendasi.index'))->assertOk()
            ->assertSee('data-sesi-batas="600"', false)
            ->assertSee('data-sesi-peringatan', false)
            ->assertSee('Sesi akan berakhir');
    }

    public function test_token_kedaluwarsa_tidak_menampilkan_page_expired(): void
    {
        Route::post('/uji-419', fn () => throw new TokenMismatchException)->middleware('web');

        $this->from(route('rekomendasi.index'))->post('/uji-419')->assertRedirect(route('masuk'))
            ->assertSessionHas('sesi_berakhir');

        $this->masuk('setba@contoh.test')->from(route('rekomendasi.index'))->post('/uji-419')
            ->assertRedirect(route('rekomendasi.index'))
            ->assertSessionHas('gagal', 'Halaman ini sudah terlalu lama dibuka. Periksa isian, lalu kirim lagi.');
    }
}
