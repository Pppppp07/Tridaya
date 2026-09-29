<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\LogAktivitas;
use App\Models\Satker;
use App\Models\User;
use App\Support\DirektoriIrm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Pengguna & hak akses di Data master (27 Sep). Kata Hizkia: Data master
 * "merupakan fitur fungsional yang mengatur hak akses pada sistem ini".
 */
class PenggunaTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        $this->masuk('setba@contoh.test');
    }

    private function pegawai(string $nama): array
    {
        return DirektoriIrm::semua()->firstWhere('nama', $nama) ?? $this->fail($nama.' tidak ada di direktori contoh');
    }

    public function test_setba_menambah_petugas_uki_dari_direktori(): void
    {
        $siti = $this->pegawai('Siti Rahmawati');

        $this->get(route('master', ['tab' => 'pengguna', 'jendela' => 'pengguna', 'q' => 'siti']))->assertOk()
            ->assertSee('Siti Rahmawati')->assertSee('nip='.$siti['nip'], false);
        /* Langkah kedua: peran yang boleh diberikan Setba saja. */
        $isi = $this->get(route('master', ['tab' => 'pengguna', 'jendela' => 'pengguna', 'nip' => $siti['nip']]))
            ->assertOk()->assertSee('Pilih perannya.')->getContent();
        foreach (['setba', 'uki', 'inspektorat', 'pimpinan'] as $p) {
            $this->assertStringContainsString('name="peran" value="'.$p.'"', $isi);
        }
        $this->assertStringNotContainsString('name="peran" value="dti"', $isi);
        $this->assertStringNotContainsString('name="peran" value="admin"', $isi);

        $this->post(route('master.pengguna.tambah'), ['nip' => $siti['nip'], 'peran' => 'uki'])->assertRedirect();
        $u = User::where('nip', $siti['nip'])->firstOrFail();
        /* Atributnya dari direktori, bukan ketikan. */
        $this->assertSame([$siti['nama'], $siti['email'], $siti['jabatan'], PeranPengguna::UKI, true],
            [$u->name, $u->email, $u->jabatan, $u->peran, $u->aktif]);
        $log = LogAktivitas::where('aksi', 'master.pengguna.tambah')->latest('id')->firstOrFail();
        $this->assertSame('uki', $log->rincian['sesudah']['peran']);
        $this->assertSame(['user', $u->id], [$log->subjek_tipe, $log->subjek_id]);

        /* Satu NIP satu akun. */
        $this->post(route('master.pengguna.tambah'), ['nip' => $siti['nip'], 'peran' => 'uki'])
            ->assertSessionHas('gagal', fn ($g) => str_contains($g, 'sudah punya akun'));
        /* Setba tidak bisa memberi peran DTI atau Admin. */
        $joko = $this->pegawai('Joko Susilo');
        $this->post(route('master.pengguna.tambah'), ['nip' => $joko['nip'], 'peran' => 'dti'])->assertSessionHasErrors('peran');
        $this->assertNull(User::where('nip', $joko['nip'])->first());
    }

    public function test_dti_dan_admin_hanya_diatur_admin(): void
    {
        $dti = User::where('email', 'dti@contoh.test')->firstOrFail();
        $this->get(route('master', ['tab' => 'pengguna']))->assertOk()->assertSee('Diatur Admin');
        $this->post(route('master.pengguna.saklar', $dti))
            ->assertSessionHas('gagal', fn ($g) => str_contains($g, 'hanya bisa diatur Admin'));
        $this->assertTrue($dti->fresh()->aktif);

        $token = $dti->remember_token;
        $this->masuk('admin@contoh.test')->post(route('master.pengguna.saklar', $dti))->assertRedirect();
        $this->assertFalse($dti->fresh()->aktif);
        $this->assertNotSame($token, $dti->fresh()->remember_token, 'Tanda ingat-saya lama harus tidak berlaku lagi.');

        /* Akun yang dinonaktifkan keluar di permintaan berikutnya. */
        $this->actingAs($dti->fresh())->get(route('log'))->assertRedirect(route('masuk'));
    }

    public function test_tidak_bisa_mengubah_diri_sendiri_dan_setba_terakhir_tetap_ada(): void
    {
        $saya = User::where('email', 'setba@contoh.test')->firstOrFail();
        $this->post(route('master.pengguna.saklar', $saya))
            ->assertSessionHas('gagal', fn ($g) => str_contains($g, 'Akun Anda sendiri'));
        $this->post(route('master.pengguna.simpan', $saya), ['peran' => 'uki'])
            ->assertSessionHas('gagal', fn ($g) => str_contains($g, 'Akun Anda sendiri'));

        /* Admin pun tidak bisa menghilangkan Setba terakhir … */
        $this->masuk('admin@contoh.test');
        $this->post(route('master.pengguna.saklar', $saya))
            ->assertSessionHas('gagal', fn ($g) => str_contains($g, 'Paling tidak satu akun Setba'));
        $this->post(route('master.pengguna.simpan', $saya), ['peran' => 'uki'])
            ->assertSessionHas('gagal', fn ($g) => str_contains($g, 'Paling tidak satu akun Setba'));
        $this->assertTrue($saya->fresh()->aktif);
        $this->assertSame(PeranPengguna::SETBA, $saya->fresh()->peran);

        /* … tapi begitu ada Setba kedua, boleh. */
        $joko = $this->pegawai('Joko Susilo');
        $this->post(route('master.pengguna.tambah'), ['nip' => $joko['nip'], 'peran' => 'setba'])->assertRedirect();
        $this->post(route('master.pengguna.simpan', $saya), ['peran' => 'uki'])->assertRedirect();
        $this->assertSame(PeranPengguna::UKI, $saya->fresh()->peran);
        $log = LogAktivitas::where('aksi', 'master.pengguna.peran')->latest('id')->firstOrFail();
        $this->assertSame([['peran' => 'setba'], ['peran' => 'uki']], [$log->rincian['sebelum'], $log->rincian['sesudah']]);
    }

    public function test_akun_satuan_kerja_diatur_lewat_unit_kerja(): void
    {
        $pj = Satker::where('kode', 'BALAI-4-BANDUNG')->firstOrFail()->penanggungJawab;
        $this->post(route('master.pengguna.saklar', $pj))
            ->assertSessionHas('gagal', fn ($g) => str_contains($g, 'Ganti lewat tab Unit kerja'));
        $this->assertTrue($pj->fresh()->aktif);
        $this->get(route('master', ['tab' => 'pengguna']))->assertOk()->assertSee('Buka di Unit kerja');
    }

    public function test_petugas_pusat_tidak_bisa_sekaligus_jadi_penanggung_jawab(): void
    {
        $bandung = Satker::where('kode', 'BALAI-4-BANDUNG')->firstOrFail();
        $sri = $this->pegawai('Sri Wahyuni');
        $this->get(route('master', ['pj' => $bandung->id, 'q' => 'Sri Wahyuni']))->assertOk()
            ->assertSee('Sudah berakun Setba');
        $this->post(route('master.unit.pj', $bandung), ['nip' => $sri['nip']])
            ->assertSessionHas('gagal', fn ($g) => str_contains($g, 'sudah punya akun sebagai Setba'));
        $this->assertNotSame($sri['nip'], $bandung->fresh()->penanggungJawab->nip);
    }

    public function test_ganti_penanggung_jawab_tercatat_dan_yang_lama_diputus(): void
    {
        $bandung = Satker::where('kode', 'BALAI-4-BANDUNG')->firstOrFail();
        $lama = $bandung->penanggungJawab;
        $token = $lama->remember_token;
        $rina = $this->pegawai('Rina Wulandari');

        $this->post(route('master.unit.pj', $bandung), ['nip' => $rina['nip']])->assertRedirect();

        $this->assertFalse($lama->fresh()->aktif);
        $this->assertNotSame($token, $lama->fresh()->remember_token);
        $log = LogAktivitas::where('aksi', 'master.unit.pj')->latest('id')->firstOrFail();
        $this->assertSame('pengguna', $log->kelompok);
        $this->assertStringContainsString($lama->name, $log->rincian['sebelum']['pj']);
        $this->assertStringContainsString($rina['nama'], $log->rincian['sesudah']['pj']);
        $this->get(route('master', ['tab' => 'riwayat', 'filter' => 'pengguna']))->assertOk()
            ->assertSee('Mengganti penanggung jawab Balai Wil. IV Bandung');
    }
}
