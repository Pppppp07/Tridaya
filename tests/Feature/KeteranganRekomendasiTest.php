<?php

namespace Tests\Feature;

use App\Enums\SumberLaporan;
use App\Models\DrafHistoris;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Support\Rangka;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/** Daftar Rekomendasi: kolom Keterangan menggantikan nomor urut — data historis atau data sistem. */
class KeteranganRekomendasiTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    private Rekomendasi $historis;

    private Rekomendasi $sistem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        $this->masuk('setba@contoh.test');
        [$this->historis, $this->sistem] = Rekomendasi::with('temuan.laporan')->get()
            ->filter(fn ($r) => $r->jenis() === SumberLaporan::LHP)->take(2)->values()->all();
        $draf = DrafHistoris::create(['user_id' => auth()->id(), 'nama_berkas' => 'pemantauan-lama.xlsx',
            'berkas' => 'historis/pemantauan-lama.xlsx', 'sha256' => str_repeat('b', 64), 'isian' => ['baris' => []]]);
        Sasaran::whereIn('id', $this->historis->sasaran()->pluck('sasarans.id'))
            ->update(['draf_historis_id' => $draf->id, 'tanggal_acuan_historis' => '2026-08-12']);
    }

    private function buka(string $alamat)
    {
        Rangka::lupakan();

        return $this->get($alamat);
    }

    public function test_kolom_keterangan_menggantikan_nomor_urut(): void
    {
        $isi = $this->buka('/rekomendasi?keadaan=semua&lingkup=semua')->assertOk()
            ->assertDontSee('<th class="num k-no">No</th>', false)
            ->assertDontSee('data-no', false)
            ->assertSeeInOrder(['k-keterangan', 'Keterangan', 'k-uraian', 'Uraian'], false)
            ->getContent();

        /* Sel pertama tiap baris: penanda asalnya, dengan keterangan lengkap di title. */
        $sel = fn (Rekomendasi $r) => preg_replace('/\s+/', ' ', explode('</td>', substr($isi, strpos($isi, 'data-rek="'.$r->id.'"')), 2)[0]);
        $this->assertStringContainsString('<span class="asal-data historis" title="Diimpor dari file pemantauan lama, keadaan per 12 Agu 2026">Data historis</span>', $sel($this->historis));
        $this->assertStringContainsString('<span class="asal-data sistem" title="Dicatat lewat Catat laporan baru">Data sistem</span>', $sel($this->sistem));
    }

    public function test_keterangan_bisa_diurutkan_dan_dicari(): void
    {
        $urutan = fn (string $alamat) => [strpos($c = $this->buka($alamat)->assertOk()->getContent(), 'data-rek="'.$this->historis->id.'"'),
            strpos($c, 'data-rek="'.$this->sistem->id.'"')];

        [$h, $s] = $urutan('/rekomendasi?keadaan=semua&urut=keterangan:naik');
        $this->assertLessThan($s, $h, 'Naik: data historis lebih dulu.');
        [$h, $s] = $urutan('/rekomendasi?keadaan=semua&urut=keterangan:turun');
        $this->assertGreaterThan($s, $h, 'Turun: data sistem lebih dulu.');

        /* Kata "historis" di kotak cari memilah daftarnya. */
        $this->buka('/rekomendasi?keadaan=semua&q=data+historis')->assertOk()
            ->assertSee('data-rek="'.$this->historis->id.'"', false)
            ->assertDontSee('data-rek="'.$this->sistem->id.'"', false)
            ->assertSee('<span data-n-tampil>1</span> rekomendasi tampil', false);
    }

    /** Pilihan tampilan di menu samping dan penanda judul sudah dibuang. */
    public function test_tanpa_pilihan_tampilan_data(): void
    {
        $this->buka('/rekomendasi?asal=historis&keadaan=semua')->assertOk()
            ->assertDontSee('Tampilan data')
            ->assertDontSee('pil-tampilan', false)
            ->assertSee('data-rek="'.$this->sistem->id.'"', false);
        $lhp = Sasaran::whereHas('tindakan.rekomendasi.temuan.laporan', fn ($q) => $q->where('sumber', 'LHP'))->count();
        $this->buka('/ringkasan?asal=historis')->assertOk()
            ->assertSee('<span>Seluruh <b>'.$lhp.'</b> tindak lanjut LHP</span>', false);
    }
}
