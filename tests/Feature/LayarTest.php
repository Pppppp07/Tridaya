<?php

namespace Tests\Feature;

use App\Http\Controllers\RekomendasiController;
use App\Models\Laporan;
use App\Models\Rekomendasi;
use App\Support\Rangka;
use App\Support\Terlihat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/** Tiap layar terbuka untuk peran yang berhak; yang tidak berhak ditolak. */
class LayarTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    public static function peranBiasa(): array
    {
        return [
            'setba' => ['setba@contoh.test'],
            'uki' => ['uki@contoh.test'],
            'inspektorat' => ['inspektorat@contoh.test'],
            'satker' => ['medan'],
        ];
    }

    #[DataProvider('peranBiasa')]
    public function test_layar_utama_terbuka(string $email): void
    {
        $this->masuk($email);

        $this->get('/rekomendasi')->assertOk();
        $this->get('/laporan')->assertOk();
        $this->get('/pemberitahuan')->assertOk();
        $this->get('/cari?q=aset')->assertOk();
    }

    public function test_beranda_mengikuti_peran(): void
    {
        $this->masuk('setba@contoh.test')->get('/')->assertRedirect(route('rekomendasi.index'));
        $this->masuk('pimpinan@contoh.test')->get('/')->assertRedirect(route('ringkasan'));
    }

    public function test_rincian_rekomendasi_dan_laporan_terbuka(): void
    {
        $this->masuk('setba@contoh.test');

        $rek = Rekomendasi::first();
        $this->get(route('rekomendasi.show', $rek))->assertOk()->assertSee($rek->kode);

        $lap = Laporan::first();
        $this->get(route('laporan.show', $lap))->assertOk()->assertSee($lap->nomor);
    }

    public function test_ringkasan_dan_data_master_hanya_untuk_yang_berhak(): void
    {
        $this->masuk('setba@contoh.test');
        $this->get('/ringkasan')->assertOk()->assertSee('Ringkasan utama');
        $this->get('/data-master')->assertForbidden();
        $this->get('/laporan/baru')->assertOk()->assertSee('Surat laporan');

        $this->masuk('admin@contoh.test');
        $this->get('/data-master')->assertOk()->assertSee('User &amp; akses', false);

        $this->masuk('medan');
        $this->get('/data-master')->assertForbidden();
        $this->get('/laporan/baru')->assertForbidden();

        $this->masuk('uki@contoh.test');
        $this->get('/data-master')->assertForbidden();
        $this->get('/laporan/baru')->assertForbidden();
    }

    public function test_tamu_diantar_ke_halaman_masuk(): void
    {
        $this->get('/rekomendasi')->assertRedirect(route('masuk'));
        $this->get('/ringkasan')->assertRedirect(route('masuk'));
    }

    /** Enter di kotak cari daftar memakai tombol bawaan tanpa nama. */
    public function test_filter_daftar_ditukar_di_tempat_dan_enter_tidak_melepas_pilihan(): void
    {
        $this->masuk('setba@contoh.test');

        /* Halaman yang ditukar sebagian tidak memakai pop-up pemberitahuan. */
        $this->get('/rekomendasi?keadaan=tunggu', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('data-ganti-di-tempat', false)->assertDontSee('data-popup', false);
        Rangka::lupakan();
        $this->get('/rekomendasi')->assertOk()->assertSee('data-popup', false);

        foreach (['/rekomendasi', '/laporan'] as $alamat) {
            Rangka::lupakan();
            $html = $this->get($alamat)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<form id="filter"[^>]*\sdata-ganti-di-tempat/', $html);
            $this->assertSame(1, preg_match('/<form id="filter".*?<\/form>/s', $html, $form));
            $this->assertSame(1, preg_match('/<button type="submit"[^>]*>/', $form[0], $pertama));
            $this->assertStringNotContainsString('name=', $pertama[0], "Tombol kirim pertama di $alamat harus tanpa nama.");
            $this->assertMatchesRegularExpression('/\shidden(?:\s|>)/', $pertama[0], "Tombol Enter di $alamat tidak boleh terlihat atau mengambil ruang.");
            $this->assertStringNotContainsString('disabled', $pertama[0], "Enter di $alamat tetap harus mengirim filter yang dipilih.");
        }
    }

    public function test_angka_keranjang_setba_sama_dengan_yang_diperagakan(): void
    {
        $halaman = $this->masuk('setba@contoh.test')->get('/rekomendasi');

        $halaman->assertOk()
            ->assertSee('Perlu dikerjakan')
            ->assertSee('Sedang menunggu')
            ->assertSee('Urusan SIPTL');

        /* 18 dari 49 rekomendasi menunggu Setba pada data contoh — angka yang
           sama tertulis di menu dan di keranjang pertama. */
        $this->assertSame(18, $this->perluDikerjakan('setba@contoh.test'));
    }

    public function test_peran_pemantau_tanpa_filter_perlu_dikerjakan(): void
    {
        foreach (['pimpinan@contoh.test', 'dti@contoh.test'] as $email) {
            $this->masuk($email)->get('/rekomendasi')->assertOk()
                ->assertDontSee('Perlu dikerjakan')
                ->assertSee('Sedang menunggu')
                ->assertSee('Sudah selesai');
            /* Tautan lama ke keranjang itu jatuh ke semua rekomendasi. */
            $this->get('/rekomendasi?keadaan=kerja')->assertOk()->assertDontSee('Perlu dikerjakan');
        }
    }

    private function perluDikerjakan(string $email): int
    {
        $u = $this->akun($email);
        $terlihat = Terlihat::untuk($u);

        return $terlihat->rekomendasi()->with(RekomendasiController::MUAT_DAFTAR)->get()
            ->map(fn ($r) => $terlihat->pangkasRekomendasi($r))
            ->filter(fn ($r) => $r->diMeja($u->peran, $u->satker_id))
            ->count();
    }
}
