<?php

namespace Tests\Feature;

use App\Models\Laporan;
use App\Models\Rekomendasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Pintasan mengantar ke sasarannya (26 Sep). Kata Hizkia: tombol pintasan harus
 * "langsung diarahkan ke pilihan tersebut dan di highlight … sesuai dengan
 * lokasi objek tersebut". Yang diuji di sini alamat dan penandanya — skripnya
 * (pasangPintasan, pasangSorot) yang membuka, menggulir, dan menyorot.
 */
class PintasanTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    /** Nama satuan kerja, "N temuan", dan "N rekomendasi" menunjuk sasaran persisnya. */
    public function test_rincian_laporan_memberi_penanda_sasaran(): void
    {
        $lap = Laporan::where('nomor', '13/LHP/XVII/05/2024')->firstOrFail();
        $isi = $this->masuk('setba@contoh.test')->get(route('laporan.show', $lap))->assertOk()->getContent();

        foreach ($lap->satkerDiperiksa() as $s) {
            $this->assertStringContainsString('data-ke-satker="'.$s->id.'"', $isi);
        }
        $this->assertStringContainsString('data-ke-temuan', $isi);
        $this->assertStringContainsString('data-ke-rek', $isi);
        $this->assertStringNotContainsString('data-tunjuk="daftar-temuan"', $isi);
        foreach ($lap->temuan as $t) {
            $this->assertMatchesRegularExpression('/data-temuan="'.$t->id.'" data-satker="[0-9|]+"/', $isi);
            foreach ($t->rekomendasi as $r) {
                $this->assertStringContainsString('data-rek="'.$r->id.'"', $isi);
            }
        }
        $this->assertStringContainsString('href="'.route('laporan.index', ['tuju' => $lap->id]).'"', $isi);
    }

    /** "Lihat laporan lengkap" dan "Kembali" menunjuk baris rekomendasi ini. */
    public function test_rincian_rekomendasi_menunjuk_baris_di_tempat_asal(): void
    {
        $rek = Rekomendasi::with('temuan.laporan')->firstOrFail();
        $setba = $this->masuk('setba@contoh.test');

        $isi = $setba->get(route('rekomendasi.show', $rek))->assertOk()->getContent();
        $this->assertStringContainsString(e(route('laporan.show', [$rek->temuan->laporan, 'temuan' => $rek->temuan->id, 'rek' => $rek->id])), $isi);
        $this->assertStringContainsString(e(route('rekomendasi.index', ['tuju' => $rek->id])), $isi);

        $dariLaporan = $setba->get(route('rekomendasi.show', ['rekomendasi' => $rek, 'dari' => 'laporan']))->getContent();
        $this->assertStringContainsString(e(route('laporan.show', [$rek->temuan->laporan, 'temuan' => $rek->temuan->id, 'rek' => $rek->id])), $dariLaporan);
    }

    /** Berkas yang sudah pindah keranjang: daftar ikut pindah ke keranjangnya. */
    public function test_tuju_memindah_keranjang_ke_tempat_berkasnya(): void
    {
        $setba = $this->akun('setba@contoh.test');
        $bukanDiMeja = Rekomendasi::all()->first(fn ($r) => ! $r->beres() && ! $r->diMeja($setba->peran, null));
        $this->assertNotNull($bukanDiMeja, 'data contoh harus punya rekomendasi yang sedang menunggu pihak lain');

        $isi = $this->actingAs($setba)->get(route('rekomendasi.index', ['tuju' => $bukanDiMeja->id]))->assertOk()->getContent();
        $this->assertStringContainsString('<input type="hidden" name="keadaan" value="tunggu">', $isi);
        $this->assertStringContainsString('data-rek="'.$bukanDiMeja->id.'"', $isi);
    }

    /** Pencarian yang cocok karena nama satuan kerja mengantar ke barisnya. */
    public function test_cari_nama_satuan_kerja_mengantar_ke_barisnya(): void
    {
        $hasil = $this->masuk('setba@contoh.test')->getJson(route('cari', ['q' => 'Makassar']))->assertOk()->json();
        $this->assertNotEmpty($hasil['rek']);
        $this->assertStringContainsString('sorot=r-tindaklanjut', $hasil['rek'][0]['link']);
        $this->assertStringContainsString('satker=', $hasil['rek'][0]['link']);
    }
}
