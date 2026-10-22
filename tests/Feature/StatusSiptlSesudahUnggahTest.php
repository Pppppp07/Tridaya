<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\StatusTindakLanjut;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Support\Dasbor;
use App\Support\TindakLanjutRingkas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Status SIPTL (termasuk BT) hanya untuk tindak lanjut yang sudah dicatat Setba diunggah; yang
 * belum diunggah tidak berstatus dan tidak dihitung di SIPTL.
 */
class StatusSiptlSesudahUnggahTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    private function hitungBpk(): array
    {
        $rek = Rekomendasi::with(['temuan.laporan', 'sasaran.satker', 'sasaran.tindakan.bentuk'])->get();

        return Dasbor::hitungTindak(Dasbor::butir($rek))['bpk'];
    }

    public function test_bt_baru_dihitung_sesudah_unggahan_dicatat(): void
    {
        /* Sudah selesai diperiksa Inspektorat, tapi belum diunggah: belum berstatus SIPTL. */
        $baris = Sasaran::with('tindakan.rekomendasi.temuan.laporan')->get()
            ->first(fn ($x) => $x->perluUnggah($x->tindakan->rekomendasi->jenis()));
        $this->assertNotNull($baris, 'Data contoh perlu memuat baris yang siap diunggah.');
        $this->assertNull($baris->statusSiptl());
        $sebelum = $this->hitungBpk();

        $this->masuk('setba@contoh.test')
            ->post(route('siptl.unggah', $baris), ['tanggal' => now()->toDateString()])
            ->assertRedirect();

        $this->assertSame(StatusTindakLanjut::BT, $baris->fresh()->statusSiptl());
        $sesudah = $this->hitungBpk();
        $this->assertSame($sebelum['n'] + 1, $sesudah['n']);
        $this->assertSame($sebelum['BT'] + 1, $sesudah['BT']);
    }

    public function test_tanpa_unggahan_tidak_berstatus_siptl(): void
    {
        $rek = Rekomendasi::with('temuan.laporan', 'sasaran')->get()
            ->first(fn ($r) => $r->jenis()->melewatiSiptl() && $r->sasaran->isNotEmpty()
                && $r->sasaran->every(fn ($x) => ! $x->siptl_tanggal));
        $this->assertNotNull($rek, 'Data contoh perlu memuat LHP yang belum diunggah sama sekali.');
        $this->assertNull($rek->statusRek());
        $this->assertNull(TindakLanjutRingkas::kemajuan($rek, PeranPengguna::SETBA, null)['rangkuman']);
        $this->assertFalse($rek->beres());

        /* Baris tindak lanjut tidak diberi label pengganti untuk yang belum diunggah. Dashboard boleh
           menghitungnya sebagai kelompok "Belum diunggah" (antrean Setba, bukan status). */
        $this->masuk('setba@contoh.test');
        foreach ([route('rekomendasi.index', ['keadaan' => 'semua']), route('rekomendasi.show', $rek),
            route('laporan.show', $rek->temuan->laporan)] as $alamat) {
            $this->get($alamat)->assertOk()->assertDontSee('Belum diunggah');
        }
    }

    public function test_sebagian_sudah_sesuai_belum_selesai(): void
    {
        $rek = Rekomendasi::with('temuan.laporan', 'sasaran')->get()
            ->first(fn ($r) => $r->jenis()->melewatiSiptl() && $r->sasaran->count() >= 2);
        $this->assertNotNull($rek, 'Data contoh perlu memuat LHP dengan dua tindak lanjut atau lebih.');
        $baris = $rek->sasaran->values();
        $sesuai = fn (Sasaran $x) => $x->forceFill(['siptl_tanggal' => '2026-08-01', 'status_bpk' => StatusTindakLanjut::SS])->save();
        $baris->slice(1)->each($sesuai);
        $baris->first()->forceFill(['siptl_tanggal' => null, 'status_bpk' => null])->save();

        /* Satu tindak lanjut belum naik: rekomendasinya belum selesai, bukan SS. */
        $rek = $rek->fresh('temuan.laporan', 'sasaran');
        $this->assertSame(StatusTindakLanjut::BS, $rek->statusRek());
        $this->assertFalse($rek->beres());

        $sesuai($baris->first());
        $rek = $rek->fresh('temuan.laporan', 'sasaran');
        $this->assertSame(StatusTindakLanjut::SS, $rek->statusRek());
        $this->assertTrue($rek->beres());
    }
}
