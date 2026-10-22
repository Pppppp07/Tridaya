<?php

namespace Tests\Feature;

use App\Models\Satker;
use App\Support\Rangka;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\PakaiDataContoh;
use Tests\TestCase;

class PerformaHalamanTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    private const BAGIAN = ['X-Requested-With' => 'XMLHttpRequest', 'X-Simtlhp-Fragment' => 'isi'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        $this->masuk('setba@contoh.test');
    }

    public function test_potongan_mengurangi_muatan_dan_menjaga_data_daftar(): void
    {
        foreach (['/rekomendasi?keadaan=semua', '/laporan?keadaan=semua'] as $url) {
            $utuh = $this->get($url)->assertOk();
            $idUtuh = $utuh->viewData('hasil')->modelKeys();
            Rangka::lupakan();
            $bagian = $this->get($url, self::BAGIAN)->assertOk()
                ->assertDontSee('<header class="top">', false)
                ->assertSee('data-ganti-di-tempat', false)
                ->assertSee('simtlhp-menu', false)
                ->assertHeader('X-Frame-Options', 'DENY');
            $this->assertSame($idUtuh, $bagian->viewData('hasil')->modelKeys());
            $this->assertLessThan(strlen($utuh->getContent()) * .85, strlen($bagian->getContent()));
        }
        // Membuka URL langsung tetap mendapat halaman lengkap, meski header fragment dikirim.
        $this->get('/laporan', ['X-Simtlhp-Fragment' => 'isi'])->assertOk()->assertSee('<header class="top">', false);
    }

    public function test_filter_dashboard_satu_respons_dengan_alamat_bersih(): void
    {
        $this->get('/ringkasan?ubah=jenis:LHA', self::BAGIAN)->assertOk()
            ->assertHeader('X-Simtlhp-Url', route('ringkasan', ['jenis' => 'LHA']))
            ->assertSeeInOrder(['7 sesuai', '12 belum sesuai'])
            ->assertDontSee('<header class="top">', false);
        $this->get('/ringkasan?ubah=jenis:LHP')->assertRedirect();
    }

    public function test_back_ke_dashboard_tanpa_filter_tidak_mengambil_filter_sesi_lama(): void
    {
        $this->withSession(['ringkasan.keadaan' => ['jenis' => 'LHA']])
            ->get('/ringkasan', self::BAGIAN)->assertOk()
            ->assertSeeInOrder(['Seluruh', '83', 'tindak lanjut LHP'])
            ->assertDontSee('tindak lanjut LHA');
    }

    public function test_potongan_tidak_memperluas_akses_satker_atau_tamu(): void
    {
        $medan = $this->akun('medan');
        $asing = Satker::where('id', '!=', $medan->satker_id)->firstOrFail();
        $this->actingAs($medan)->get('/rekomendasi?keadaan=semua', self::BAGIAN)->assertOk()
            ->assertDontSee($asing->nama)->assertDontSee('Semua satuan kerja');
        $this->actingAs($medan)->get('/ringkasan', self::BAGIAN)->assertOk()
            ->assertViewHas('d', fn ($d) => collect($d['K'])->isNotEmpty()
                && collect($d['K'])->every(fn ($x) => $x['baris']->every(fn ($b) => $b->satker_id === $medan->satker_id)));
        auth()->logout();
        $this->get('/laporan', self::BAGIAN)->assertRedirect(route('masuk'));
    }

    public function test_potongan_form_menjaga_validasi_dan_isian_draf(): void
    {
        $this->post('/laporan/baru', ['aksi' => 'maju', 'surat' => ['nomor' => 'DRAF/PERFORMA']], self::BAGIAN)
            ->assertOk()->assertSee('class="pesan bad"', false)
            ->assertSee('DRAF/PERFORMA')->assertSee('data-form-baru', false)
            ->assertDontSee('<header class="top">', false);
    }

    public function test_pencarian_tidak_memuat_arsip_bukti_dan_riwayat(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->getJson('/cari?q=balai')->assertOk()->assertJsonStructure(['rek', 'lap', 'jumlah']);
        $kueri = collect(DB::getQueryLog())->pluck('query')->join("\n");
        DB::disableQueryLog();
        foreach (['riwayat_berkas', 'lampirans', 'pemulihans', 'permintaan_dokumens', 'item_permintaans'] as $tabel) {
            $this->assertStringNotContainsString('from "'.$tabel.'"', $kueri);
        }
    }

    public function test_filter_banyak_satker_tidak_memunculkan_kueri_per_satker(): void
    {
        $url = route('ringkasan', ['satker' => Satker::pluck('id')->all()]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get($url, self::BAGIAN)->assertOk();
        $perSatker = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], '"satkers"."id" = ?'));
        DB::disableQueryLog();
        $this->assertCount(0, $perSatker);
    }
}
