<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\SumberLaporan;
use App\Models\DrafHistoris;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Support\Rangka;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/** Dashboard: tab Semua data · Data sistem · Data historis di atas filter, tiap tab tampilannya sendiri. */
class DasborDataHistorisTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    private int $lhp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        $this->masuk('setba@contoh.test');
        $this->lhp = Sasaran::whereHas('tindakan.rekomendasi.temuan.laporan', fn ($q) => $q->where('sumber', 'LHP'))->count();
    }

    private function buka(string $alamat)
    {
        Rangka::lupakan();

        return $this->get($alamat);
    }

    /** Seluruh rekomendasi satu laporan LHP dijadikan historis; hasilnya jumlah tindak lanjutnya. */
    private function jadikanHistoris(): int
    {
        $rek = Rekomendasi::with('sasaran', 'temuan.laporan')->get()
            ->first(fn ($r) => $r->jenis() === SumberLaporan::LHP && $r->diMeja(PeranPengguna::SETBA));
        $ids = Rekomendasi::whereHas('temuan', fn ($q) => $q->where('laporan_id', $rek->temuan->laporan_id))->pluck('id');
        $draf = DrafHistoris::create(['user_id' => auth()->id(), 'nama_berkas' => 'pemantauan-lama.xlsx',
            'berkas' => 'historis/pemantauan-lama.xlsx', 'sha256' => str_repeat('b', 64), 'isian' => ['baris' => []]]);

        return Sasaran::whereIn('tindakan_id', fn ($q) => $q->select('id')->from('tindakans')->whereIn('rekomendasi_id', $ids))
            ->update(['draf_historis_id' => $draf->id, 'tanggal_acuan_historis' => '2026-08-12']);
    }

    public function test_tanpa_data_historis_tidak_ada_tab(): void
    {
        $this->buka('/ringkasan')->assertOk()->assertDontSee('aria-label="Data yang ditampilkan"', false);
        $this->buka('/ringkasan?data=historis')->assertOk()
            ->assertSee('<span>Seluruh <b>'.$this->lhp.'</b> tindak lanjut LHP</span>', false)
            ->assertDontSee('dsb-data-sebut', false);
    }

    public function test_tab_di_atas_filter_dengan_angkanya(): void
    {
        $n = $this->jadikanHistoris();

        $this->buka('/ringkasan')->assertOk()
            ->assertSeeInOrder(['aria-label="Data yang ditampilkan"',
                'value="data:semua"', 'aria-selected="true"', 'Semua data', '<span class="dm-n">'.$this->lhp.'</span>',
                'value="data:sistem"', 'Data sistem', '<span class="dm-n">'.($this->lhp - $n).'</span>',
                'value="data:historis"', 'Data historis', '<span class="dm-n">'.$n.'</span>',
                'aria-label="Filter dashboard"'], false)
            ->assertSee('<span>Seluruh <b>'.$this->lhp.'</b> tindak lanjut LHP</span>', false);
    }

    public function test_tab_historis_menampilkan_data_historis_saja(): void
    {
        $n = $this->jadikanHistoris();

        $this->buka('/ringkasan?ubah=data:historis')->assertRedirect(route('ringkasan', ['data' => 'historis']));

        /* Tampilan, bukan filter: acuan "Seluruh N" ikut, tanpa "Hapus filter". */
        $this->buka('/ringkasan?data=historis')->assertOk()
            ->assertSee('<span>Seluruh <b>'.$n.'</b> tindak lanjut LHP</span>', false)
            ->assertSee('<span class="dsb-data-sebut">· Data historis</span>', false)
            ->assertSeeInOrder(['value="data:historis"', 'aria-selected="true"'], false)
            ->assertDontSee('Hapus filter');
        $this->buka('/ringkasan?data=sistem')->assertOk()
            ->assertSee('<span>Seluruh <b>'.($this->lhp - $n).'</b> tindak lanjut LHP</span>', false)
            ->assertSee('<span class="dsb-data-sebut">· Data sistem</span>', false);
        $this->buka('/ringkasan?data=historis&hal=tabel')->assertOk()
            ->assertSee('aria-label="'.$n.' tindak lanjut, semua tahun', false);

        /* Data historis tidak punya tanggal selesai di sistem: tanpa grafik Per bulan. */
        $this->buka('/ringkasan?data=historis&kartu=M')->assertOk()
            ->assertSee('<h2 id="dsb-rincian-judul">Status</h2>', false)->assertDontSee('Per bulan');
        $this->buka('/ringkasan?data=sistem&kartu=M')->assertOk()->assertSee('Per bulan');
    }

    /** Seperti data dev sekarang: semuanya historis. Tab yang kosong tidak menyuruh menghapus filter. */
    public function test_tab_tanpa_data_menyebut_sebabnya(): void
    {
        $draf = DrafHistoris::create(['user_id' => auth()->id(), 'nama_berkas' => 'pemantauan-lama.xlsx',
            'berkas' => 'historis/pemantauan-lama.xlsx', 'sha256' => str_repeat('b', 64), 'isian' => ['baris' => []]]);
        Sasaran::query()->update(['draf_historis_id' => $draf->id, 'tanggal_acuan_historis' => '2026-08-12']);

        $this->buka('/ringkasan?data=sistem')->assertOk()
            ->assertSee('Belum ada tindak lanjut LHP di data sistem.')
            ->assertSee('name="ubah" value="data:semua" class="btn btn-s"', false)
            ->assertDontSee('Hapus semua filter');
    }

    public function test_tab_tetap_saat_filter_dihapus_dan_dibuka_lagi_lewat_menu(): void
    {
        $this->jadikanHistoris();

        $this->buka('/ringkasan?data=historis&tahun[]=2024&ubah=kosongkan')
            ->assertRedirect(route('ringkasan', ['data' => 'historis']));
        $this->buka('/ringkasan?data=historis&ubah=kartu:M')
            ->assertRedirect(route('ringkasan', ['data' => 'historis', 'kartu' => 'M']));
        /* Dibuka lewat menu: tab dan kartu terakhir dibawa lagi. */
        $this->buka('/ringkasan')->assertRedirect(route('ringkasan', ['data' => 'historis', 'kartu' => 'M']));
        $this->buka('/ringkasan?ubah=data:semua&kartu=M&data=historis')->assertRedirect(route('ringkasan', ['kartu' => 'M']));
    }
}
