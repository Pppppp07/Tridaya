<?php

namespace Tests\Feature;

use App\Enums\SumberLaporan;
use App\Models\Laporan;
use App\Support\Rangka;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/** Daftar laporan punya tombol LHP/LHA sendiri; pilihannya sama dengan layar Rekomendasi. */
class DaftarLaporanJenisTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        $this->masuk('setba@contoh.test');
    }

    private function buka(string $alamat)
    {
        Rangka::lupakan();

        return $this->get($alamat);
    }

    public function test_tombol_lhp_lha_tampil_dengan_jumlahnya(): void
    {
        $lhp = Laporan::where('sumber', SumberLaporan::LHP)->count();
        $lha = Laporan::where('sumber', SumberLaporan::LHA)->count();

        $this->buka('/laporan?keadaan=semua')->assertOk()
            ->assertSee('aria-label="Jenis laporan"', false)
            ->assertSeeInOrder(['name="lingkup" value="semua" aria-pressed="true"', '<span class="n">'.($lhp + $lha).'</span>',
                'name="lingkup" value="LHP"', '<span class="n">'.$lhp.'</span>',
                'name="lingkup" value="LHA"', '<span class="n">'.$lha.'</span>',
                'aria-label="Keadaan laporan"'], false);
    }

    public function test_memilih_lha_menyaring_dan_ikut_ke_rekomendasi(): void
    {
        $lha = Laporan::where('sumber', SumberLaporan::LHA)->firstOrFail();
        $lhp = Laporan::where('sumber', SumberLaporan::LHP)->firstOrFail();

        $this->buka('/laporan?keadaan=semua&lingkup=LHA')->assertOk()
            ->assertSee('name="lingkup" value="LHA" aria-pressed="true"', false)
            ->assertSee('data-lap="'.$lha->id.'"', false)
            ->assertDontSee('data-lap="'.$lhp->id.'"', false);

        /* Satu pilihan untuk kedua layar (disimpan di sesi). */
        $this->buka('/rekomendasi?keadaan=semua')->assertOk()
            ->assertSee('name="lingkup" value="LHA" aria-pressed="true"', false);
        $this->buka('/laporan?keadaan=semua')->assertOk()
            ->assertSee('name="lingkup" value="LHA" aria-pressed="true"', false);

        $this->buka('/laporan?keadaan=semua&lingkup=semua')->assertOk()
            ->assertSee('data-lap="'.$lha->id.'"', false)
            ->assertSee('data-lap="'.$lhp->id.'"', false);
    }

    public function test_kosong_karena_pilihan_jenis_tidak_disebut_belum_ada_laporan(): void
    {
        Laporan::where('sumber', SumberLaporan::LHA)->get()->each(fn ($l) => $l->update(['sumber' => SumberLaporan::LHP]));

        $this->buka('/laporan?keadaan=semua&lingkup=LHA')->assertOk()
            ->assertSee('Tidak ada laporan yang sesuai. Ubah kata kunci atau filter.')
            ->assertDontSee('Belum ada laporan yang dicatat.');
    }
}
