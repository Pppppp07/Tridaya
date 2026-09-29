<?php

namespace Tests\Feature;

use App\Models\Laporan;
use App\Models\Rekomendasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Tiap layar terbuka untuk tiap peran yang berhak — dan yang tidak berhak
 * ditolak, bukan dibiarkan melihat halaman kosong.
 *
 * Uji paling murah yang ada, dan yang paling sering menangkap: satu nama view
 * yang salah ketik atau satu pembantu yang terhapus langsung terlihat di sini.
 */
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

    #[\PHPUnit\Framework\Attributes\DataProvider('peranBiasa')]
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
        $this->get('/data-master')->assertOk()->assertSee('Kategori internal');
        $this->get('/laporan/baru')->assertOk()->assertSee('Surat laporan');

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

    /**
     * Kepala daftar Rekomendasi dan Daftar laporan ditukar di tempat oleh
     * skrip (29 Sep). Enter di kotak carinya memakai tombol bawaan tanpa
     * nama — bukan keping pertama, yang diam-diam melepas pilihan jenis
     * laporan atau keadaan yang sedang menyala.
     */
    public function test_filter_daftar_ditukar_di_tempat_dan_enter_tidak_melepas_pilihan(): void
    {
        $this->masuk('setba@contoh.test');

        /* Halaman yang diminta skrip untuk ditukar sebagian tidak memakai
           pop-up pemberitahuan — kalau dipakai, pemberitahuannya tercatat
           sudah dimunculkan padahal tidak pernah tampil. */
        $this->get('/rekomendasi?keadaan=tunggu', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('data-ganti-di-tempat', false)->assertDontSee('data-popup', false);
        \App\Support\Rangka::lupakan();
        $this->get('/rekomendasi')->assertOk()->assertSee('data-popup', false);

        foreach (['/rekomendasi', '/laporan'] as $alamat) {
            \App\Support\Rangka::lupakan();
            $html = $this->get($alamat)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<form id="filter"[^>]*\sdata-ganti-di-tempat/', $html);
            $this->assertSame(1, preg_match('/<form id="filter".*?<\/form>/s', $html, $form));
            $this->assertSame(1, preg_match('/<button type="submit"[^>]*>/', $form[0], $pertama));
            $this->assertStringNotContainsString('name=', $pertama[0], "Tombol kirim pertama di $alamat harus tanpa nama.");
        }
    }

    public function test_angka_keranjang_setba_sama_dengan_yang_diperagakan(): void
    {
        $halaman = $this->masuk('setba@contoh.test')->get('/rekomendasi');

        $halaman->assertOk()
            ->assertSee('Perlu dikerjakan')
            ->assertSee('Sedang menunggu')
            ->assertSee('Urusan SIPTL');

        /* 19 dari 49 rekomendasi menunggu Setba pada data contoh — angka yang
           sama tertulis di menu dan di keranjang pertama. */
        $this->assertSame(19, $this->perluDikerjakan('setba@contoh.test'));
    }

    private function perluDikerjakan(string $email): int
    {
        $u = $this->akun($email);
        $terlihat = \App\Support\Terlihat::untuk($u);

        return $terlihat->rekomendasi()->with(\App\Http\Controllers\RekomendasiController::MUAT_DAFTAR)->get()
            ->map(fn ($r) => $terlihat->pangkasRekomendasi($r))
            ->filter(fn ($r) => $r->diMeja($u->peran, $u->satker_id))
            ->count();
    }
}
