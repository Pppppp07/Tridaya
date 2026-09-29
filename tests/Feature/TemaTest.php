<?php

namespace Tests\Feature;

use App\Models\LogAktivitas;
use App\Support\Rangka;
use App\Support\Setelan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

class TemaTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    public function test_default_terang_dan_pilihan_hanya_milik_akun_yang_masuk(): void
    {
        $setba = $this->akun('setba@contoh.test');
        $lain = $this->akun('pimpinan@contoh.test');
        $this->assertSame('terang', $setba->setelan('tema'));
        $this->assertSame('otomatis', $setba->setelan('menu'));

        $this->actingAs($setba)->postJson(route('akun.tampilan'), [
            'tema' => 'gelap', 'menu' => 'ringkas', 'user_id' => $lain->id,
        ])->assertOk()->assertJsonPath('tampilan.tema', 'gelap');
        $this->assertSame('gelap', $setba->fresh()->setelan('tema'));
        $this->assertSame('ringkas', $setba->fresh()->setelan('menu'));
        $this->assertSame('terang', $lain->fresh()->setelan('tema'));

        // Server menggambar tema sebelum JS berjalan, juga pada permintaan berikutnya.
        foreach (['rekomendasi.index', 'laporan.index', 'ringkasan', 'master', 'akun', 'pemberitahuan'] as $rute) {
            $this->actingAs($setba->fresh())->get(route($rute))->assertOk()
                ->assertSee('data-tema="gelap"', false)->assertSee('data-mode-menu="ringkas"', false);
        }
        Rangka::lupakan();
        $this->actingAs($lain)->get(route('akun'))->assertOk()->assertSee('data-tema="terang"', false);
    }

    public function test_pintasan_tidak_menimpa_pengaturan_lain_dan_tidak_mencatat_perubahan_palsu(): void
    {
        $u = $this->akun('setba@contoh.test');
        $u->forceFill(['pengaturan' => ['animasi' => 'kurang', 'beranda' => 'laporan', 'email' => true]])->save();
        $this->actingAs($u)->postJson(route('akun.tampilan'), ['tema' => 'gelap'])->assertOk();
        $this->postJson(route('akun.tampilan'), ['tema' => 'gelap'])->assertOk();
        $this->assertSame('kurang', $u->fresh()->setelan('animasi'));
        $this->assertSame('laporan', $u->fresh()->setelan('beranda'));
        $this->assertTrue($u->fresh()->setelan('email'));
        $this->assertSame(1, LogAktivitas::where('aksi', 'akun.setelan')->where('user_id', $u->id)->count());

        $this->postJson(route('akun.tampilan'), ['tema' => 'terang'])->assertOk();
        $this->assertSame('terang', $u->fresh()->setelan('tema'));
    }

    public function test_validasi_dan_tab_akun_lama_tidak_mengubah_akun_baru(): void
    {
        $this->postJson(route('akun.tampilan'), ['tema' => 'gelap'])->assertUnauthorized();
        $u = $this->akun('setba@contoh.test');
        $this->actingAs($u)->postJson(route('akun.tampilan'), ['tema' => 'otomatis'])->assertUnprocessable();
        $this->postJson(route('akun.tampilan'), ['menu' => 'invalid'])->assertUnprocessable();
        $this->postJson(route('akun.tampilan'), [])->assertUnprocessable();
        $this->postJson(route('akun.tampilan'), ['tema' => 'gelap', 'akun' => $this->akun('pimpinan@contoh.test')->id])->assertConflict();
        $this->assertSame('terang', $u->fresh()->setelan('tema'));
    }

    public function test_form_tampilan_dan_pintasan_tanpa_javascript_tersimpan(): void
    {
        $u = $this->akun('dti@contoh.test');
        $this->actingAs($u)->post(route('akun.setelan'), [
            'bagian' => 'tampilan', 'tema' => 'gelap', 'menu' => 'lebar', 'animasi' => 'kurang', 'beranda' => 'log',
        ])->assertRedirect(route('akun', ['tab' => 'tampilan']));
        $this->get(route('akun', ['tab' => 'tampilan']))->assertOk()->assertSee('data-tema="gelap"', false);
        $this->assertSame('lebar', $u->fresh()->setelan('menu'));
        $this->from(route('akun'))->post(route('akun.tampilan'), ['tema' => 'terang'])->assertRedirect(route('akun'));
        $this->assertSame('terang', $u->fresh()->setelan('tema'));
    }

    public function test_model_lama_tidak_menghilangkan_pengaturan_yang_baru_disimpan(): void
    {
        $u = $this->akun('setba@contoh.test');
        $salinanLama = $u->fresh();
        $this->actingAs($u);
        Setelan::simpan($u, ['tema' => 'gelap'], 'tampilan');
        Setelan::simpan($salinanLama, ['menu' => 'ringkas'], 'tampilan');
        $this->assertSame('gelap', $u->fresh()->setelan('tema'));
        $this->assertSame('ringkas', $u->fresh()->setelan('menu'));
    }
}
