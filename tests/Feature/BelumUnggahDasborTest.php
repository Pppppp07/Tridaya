<?php

namespace Tests\Feature;

use App\Models\Rekomendasi;
use App\Support\Dasbor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Dashboard: "Belum diunggah" (sudah memadai, unggahannya ke SIPTL belum dicatat) selalu tampil;
 * kotak-kotak rincian kartu adalah tombol filter seperti keping filter utama; Tabel keseluruhan
 * memakai tindak lanjut sebagai acuan.
 */
class BelumUnggahDasborTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        $this->masuk('setba@contoh.test');
    }

    private function butir(): array
    {
        return Dasbor::butir(Rekomendasi::with('temuan.laporan', 'tindakan.sasaran.satker')->get());
    }

    public function test_belum_diunggah_dihitung_dari_baris_memadai_yang_belum_dicatat_di_siptl(): void
    {
        $items = $this->butir();
        $harap = 0;
        foreach ($items as $x) {
            $harap += $x['semua']->filter(fn ($b) => $b->perluUnggah($x['lap']->sumber))->count();
        }
        $this->assertGreaterThan(0, $harap, 'Data contoh perlu memuat baris yang belum diunggah.');
        $this->assertSame($harap, Dasbor::hitungTindak($items)['belumUnggah']);

        /* Yang memadai di BPSDM = sudah di SIPTL + belum diunggah + LHA. */
        $bd = Dasbor::hitungBanding($items);
        $this->assertSame($harap, $bd['belumUnggah']['n']);
        $this->assertSame($bd['unor']['M']['n'], $bd['mSiptl'] + $bd['belumUnggah']['n'] + $bd['mLha']);
    }

    public function test_kotak_rincian_semua_kartu_adalah_tombol_filter(): void
    {
        $harap = [
            'M' => ['hasil:M', 'hasil:BM'],
            'tunggu' => ['meja:satker', 'meja:setba'],
            'sisa' => ['hasil:M', 'hasil:BM'],
            'ss' => ['bpk:SS', 'bpk:BS', 'bpk:BT', 'bpk:belumUnggah'],
        ];
        foreach ($harap as $kartu => $ubah) {
            $isi = $this->get('/ringkasan?kartu='.$kartu)->assertOk()->getContent();
            foreach ($ubah as $u) {
                $this->assertMatchesRegularExpression('/value="'.preg_quote($u.'@dsb-rincian', '/').'" aria-pressed="false"\s+class="dsb-bag"/', $isi,
                    "Kartu $kartu: kotak $u harus tombol filter.");
            }
        }
        /* Tidak ada lagi sakelar terpisah. */
        $this->get('/ringkasan?kartu=ss')->assertDontSee('dsb-sakelar');
    }

    public function test_kotak_bekerja_seperti_keping_filter_utama(): void
    {
        $jawab = $this->get('/ringkasan?kartu=ss&ubah='.urlencode('bpk:belumUnggah@dsb-rincian'));
        $jawab->assertRedirect();
        $this->assertStringContainsString('bpk', urldecode($jawab->headers->get('Location')));
        $this->assertStringContainsString('belumUnggah', $jawab->headers->get('Location'));

        /* Yang dipilih menyala dan tampil di bar filter; kotak lain tetap ada dengan angkanya (dihitung
           tanpa filternya sendiri), dipudarkan. */
        $isi = $this->get('/ringkasan?kartu=ss&bpk[]=belumUnggah')->assertOk()
            ->assertSee('Hapus filter Belum diunggah ke SIPTL')
            ->assertSeeInOrder(['31', 'SS · Sudah sesuai', '2', 'Belum diunggah'])
            ->getContent();
        $this->assertMatchesRegularExpression('/value="bpk:belumUnggah@dsb-rincian" aria-pressed="true"\s+class="dsb-bag"/', $isi);
        $this->assertMatchesRegularExpression('/value="bpk:SS@dsb-rincian" aria-pressed="false"\s+class="dsb-bag redup"/', $isi);

        /* Satu meja dipilih: kotak meja lain tetap tampil dengan angkanya. */
        $isi = $this->get('/ringkasan?kartu=tunggu&meja[]=satker')->assertOk()
            ->assertSeeInOrder(['24', 'di satuan kerja', '11', 'di Setba'])
            ->getContent();
        $this->assertMatchesRegularExpression('/value="meja:setba@dsb-rincian" aria-pressed="false"\s+class="dsb-bag redup"/', $isi);
    }

    public function test_potongan_belum_diunggah_memfilter_barisnya(): void
    {
        $items = $this->butir();
        $f = array_merge(Dasbor::FILTER_KOSONG, ['bpk' => ['belumUnggah']]);
        $baris = collect(Dasbor::terapkanFilter($items, $f))->flatMap(fn ($x) => $x['baris']->map(fn ($b) => [$b, $x]));
        $this->assertCount(Dasbor::hitungTindak($items)['belumUnggah'], $baris);
        $baris->each(fn ($p) => $this->assertTrue($p[0]->perluUnggah($p[1]['lap']->sumber)));
    }

    public function test_tabel_keseluruhan_acuannya_tindak_lanjut(): void
    {
        $this->get('/ringkasan?hal=tabel&buka[]=2026')->assertOk()
            ->assertSee('<span>Belum diunggah</span>', false)
            ->assertDontSee('<span>Rekomendasi</span>', false)
            ->assertDontSee('dsb-tk-rek')
            ->assertDontSee('dsb-sakelar')
            ->assertSeeInOrder(['Jumlah seluruhnya', '83']);

        /* Angka di kolom Belum diunggah membuka rekomendasinya. */
        $this->get('/ringkasan?hal=tabel&ubah='.urlencode('pintas:||belumUnggah@dsb-pintas'))->assertRedirect();
    }
}
