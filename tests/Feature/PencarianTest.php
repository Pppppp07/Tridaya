<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Laporan;
use App\Models\Rekomendasi;
use App\Models\Satker;
use App\Models\Temuan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

class PencarianTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    private Rekomendasi $bersama;

    private Satker $pertama;

    private Satker $kedua;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        $this->bersama = Rekomendasi::with('sasaran.satker')->where('kode', '2024.13.I.7.b')->firstOrFail();
        $satker = $this->bersama->daftarSasaran()->pluck('satker')->unique('id')->values();
        $this->assertCount(2, $satker);
        $this->pertama = $satker[0];
        $this->kedua = $satker[1];
        $this->pertama->update(['nama' => 'Unit Pengelola Alpha', 'nama_pendek' => 'Alpha 01']);
        $this->kedua->update(['nama' => 'Unit Pendamping Beta', 'nama_pendek' => 'Beta 02']);
    }

    public function test_semua_sasaran_dapat_dicari_dengan_nama_lengkap_dan_pendek(): void
    {
        foreach ([$this->pertama, $this->kedua] as $satker) {
            foreach ([$satker->nama, $satker->namaPendek()] as $kata) {
                $hasil = $this->masuk('setba@contoh.test')->getJson(route('cari', ['q' => $kata]))->assertOk()->json('rek');
                $rek = collect($hasil)->firstWhere('kode', $this->bersama->kode);
                $this->assertNotNull($rek, $kata);
                $this->assertStringContainsString('satker='.$satker->id, $rek['link']);
                $this->assertStringContainsString('sorot=r-tindaklanjut', $rek['link']);
                $this->assertStringContainsString($satker->namaPendek(), $rek['satker']);

                $this->masuk('setba@contoh.test')->get(route('rekomendasi.index', ['q' => $kata, 'keadaan' => 'semua']))
                    ->assertOk()->assertViewHas('hasil', fn ($hasil) => $hasil->contains('id', $this->bersama->id));
            }
        }
    }

    public function test_satuan_kerja_hanya_mencari_nama_sasaran_yang_boleh_dilihat(): void
    {
        $pengguna = $this->pertama->penanggungJawab;
        $this->assertNotNull($pengguna);

        foreach ([$this->kedua->nama, $this->kedua->namaPendek()] as $kata) {
            $this->actingAs($pengguna)->getJson(route('cari', ['q' => $kata]))->assertOk()
                ->assertJsonPath('rek', [])->assertJsonPath('lap', [])->assertJsonPath('jumlah', 0);
            $this->actingAs($pengguna)->get(route('rekomendasi.index', ['q' => $kata, 'keadaan' => 'semua']))
                ->assertOk()->assertViewHas('hasil', fn ($hasil) => $hasil->isEmpty());
        }

        $jawaban = $this->actingAs($pengguna)->getJson(route('cari', ['q' => $this->bersama->kode]))->assertOk();
        $this->assertSame($this->pertama->namaPendek(), $jawaban->json('rek.0.satker'));
        $this->assertStringNotContainsString($this->kedua->namaPendek(), $jawaban->getContent());

        $halaman = $this->actingAs($pengguna)->get(route('rekomendasi.index', ['keadaan' => 'semua']))->assertOk();
        $this->assertStringNotContainsString($this->kedua->nama, $halaman->getContent());
        $this->assertStringNotContainsString($this->kedua->namaPendek(), $halaman->getContent());
    }

    public function test_rekomendasi_di_luar_penugasan_tidak_muncul_walau_kodenya_diketahui(): void
    {
        $pengguna = $this->pertama->penanggungJawab;
        $asing = Rekomendasi::whereDoesntHave('sasaran', fn ($s) => $s->where('satker_id', $this->pertama->id))->firstOrFail();
        $this->actingAs($pengguna)->getJson(route('cari', ['q' => $asing->kode]))->assertOk()->assertJsonPath('rek', []);
        $this->actingAs($pengguna)->get(route('rekomendasi.index', ['q' => $asing->kode, 'keadaan' => 'semua']))
            ->assertOk()->assertViewHas('hasil', fn ($hasil) => $hasil->isEmpty());
    }

    public function test_lingkup_jenis_dan_kata_tidak_dikenal_tetap_menyaring_hasil(): void
    {
        $this->masuk('setba@contoh.test')->getJson(route('cari', ['q' => $this->bersama->kode, 'lingkup' => 'LHA']))
            ->assertOk()->assertJsonPath('rek', []);
        $this->masuk('setba@contoh.test')->get(route('rekomendasi.index', [
            'q' => $this->kedua->namaPendek(), 'lingkup' => 'LHA', 'keadaan' => 'semua',
        ]))->assertOk()->assertViewHas('hasil', fn ($hasil) => ! $hasil->contains('id', $this->bersama->id));
        $this->masuk('setba@contoh.test')->getJson(route('cari', ['q' => 'unit-yang-tidak-ada']))
            ->assertOk()->assertJsonPath('jumlah', 0);
        $this->masuk('setba@contoh.test')->getJson(route('cari', ['q' => ' ']))
            ->assertOk()->assertExactJson(['rek' => [], 'lap' => [], 'jumlah' => 0]);
    }

    public function test_nama_sasaran_berisi_markup_tidak_menjadi_html_pada_tabel(): void
    {
        $markup = '<img src=x onerror=alert(1)> & Beta';
        $this->kedua->update(['nama' => $markup, 'nama_pendek' => $markup]);
        $halaman = $this->masuk('setba@contoh.test')->get(route('rekomendasi.index', [
            'q' => 'onerror=alert(1)', 'keadaan' => 'semua',
        ]))->assertOk()->assertViewHas('hasil', fn ($hasil) => $hasil->contains('id', $this->bersama->id));
        $halaman->assertSee(e($markup), false)->assertDontSee($markup, false);
        $hasil = $this->masuk('setba@contoh.test')->getJson(route('cari', ['q' => 'onerror=alert(1)']))->assertOk()->json('rek');
        $this->assertTrue(collect($hasil)->contains('kode', $this->bersama->kode));
    }

    public function test_pengunjung_tidak_dapat_memanggil_pencarian(): void
    {
        $this->get(route('cari', ['q' => $this->bersama->kode]))->assertRedirect(route('masuk'));
    }

    /** Temuan bersama tanpa penugasan: hak membaca berasal dari lokasi pemeriksaan. */
    private function laporanBersama(): array
    {
        $pertama = Satker::create(['kode' => 'GAMMA', 'nama' => 'Pusat Pemeriksaan Gamma', 'nama_pendek' => 'Gamma 03']);
        $kedua = Satker::create(['kode' => 'DELTA', 'nama' => 'Pusat Pemeriksaan Delta', 'nama_pendek' => 'Delta 04']);
        $laporan = Laporan::create([
            'sumber' => 'LHP', 'nomor' => '99/LHP/UJI/2026', 'tgl_surat' => '2026-08-01', 'tgl_terima' => '2026-08-02',
        ]);
        $temuan = Temuan::create(['laporan_id' => $laporan->id, 'kode' => '2026.99.I.1', 'judul' => 'Perbaikan administrasi bersama']);
        $temuan->satkers()->attach([$pertama->id, $kedua->id]);
        $pengguna = User::factory()->create(['peran' => PeranPengguna::SATKER, 'satker_id' => $pertama->id, 'aktif' => true]);

        return [$laporan, $pertama, $kedua, $pengguna];
    }

    public function test_laporan_multi_satker_dapat_dicari_dengan_nama_lengkap_dan_pendek(): void
    {
        [$laporan, $pertama, $kedua] = $this->laporanBersama();
        foreach ([$pertama, $kedua] as $satker) {
            foreach ([$satker->nama, $satker->namaPendek()] as $kata) {
                $this->masuk('setba@contoh.test')->getJson(route('cari', ['q' => $kata]))->assertOk()
                    ->assertJsonPath('lap.0.nomor', $laporan->nomor);
                $this->masuk('setba@contoh.test')->get(route('laporan.index', ['cari' => $kata, 'keadaan' => 'semua']))
                    ->assertOk()->assertViewHas('hasil', fn ($hasil) => $hasil->contains('id', $laporan->id));
            }
        }
    }

    public function test_pencarian_laporan_tidak_membocorkan_nama_satker_lain(): void
    {
        [$laporan, $pertama, $kedua, $pengguna] = $this->laporanBersama();
        foreach ([$kedua->nama, $kedua->namaPendek()] as $kata) {
            $this->actingAs($pengguna)->getJson(route('cari', ['q' => $kata]))->assertOk()
                ->assertJsonPath('lap', [])->assertJsonPath('jumlah', 0);
            $this->actingAs($pengguna)->get(route('laporan.index', ['cari' => $kata, 'keadaan' => 'semua']))
                ->assertOk()->assertViewHas('hasil', fn ($hasil) => $hasil->isEmpty());
        }

        $jawaban = $this->actingAs($pengguna)->getJson(route('cari', ['q' => $pertama->namaPendek()]))->assertOk()
            ->assertJsonPath('lap.0.nomor', $laporan->nomor);
        $this->assertStringContainsString($pertama->namaPendek(), $jawaban->json('lap.0.satker'));
        $this->assertStringNotContainsString($kedua->namaPendek(), $jawaban->getContent());
        $halaman = $this->actingAs($pengguna)->get(route('laporan.index', ['cari' => $pertama->namaPendek(), 'keadaan' => 'semua']))
            ->assertOk()->assertViewHas('hasil', fn ($hasil) => $hasil->contains('id', $laporan->id));
        $this->assertStringNotContainsString($kedua->nama, $halaman->getContent());
        $this->assertStringNotContainsString($kedua->namaPendek(), $halaman->getContent());
    }
}
