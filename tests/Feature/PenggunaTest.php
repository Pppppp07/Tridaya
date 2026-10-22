<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\LogAktivitas;
use App\Models\Satker;
use App\Models\User;
use App\Support\DirektoriIrm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/** Pengguna & hak akses di Data master. */
class PenggunaTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        $this->masuk('admin@contoh.test');
    }

    private function pegawai(string $nama): array
    {
        return DirektoriIrm::semua()->firstWhere('nama', $nama) ?? $this->fail($nama.' tidak ada di direktori contoh');
    }

    public function test_admin_menambah_petugas_uki_dari_direktori(): void
    {
        $siti = $this->pegawai('Siti Rahmawati');

        $this->get(route('master', ['tab' => 'pengguna', 'jendela' => 'pengguna', 'q' => 'siti']))->assertOk()
            ->assertSee('Siti Rahmawati')->assertSee('nip='.$siti['nip'], false);
        /* Admin DTI memberi semua peran pusat; Satker tetap lewat unit. */
        $isi = $this->get(route('master', ['tab' => 'pengguna', 'jendela' => 'pengguna', 'nip' => $siti['nip']]))
            ->assertOk()->assertSee('Pilih satu atau beberapa peran user.')->getContent();
        foreach (['setba', 'uki', 'inspektorat', 'pimpinan', 'dti', 'admin'] as $p) {
            $this->assertStringContainsString('name="perans[]" value="'.$p.'"', $isi);
        }
        $this->assertStringNotContainsString('name="perans[]" value="satker"', $isi);

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
        /* Akun Satker tidak dibuat lewat peran pusat. */
        $joko = $this->pegawai('Joko Susilo');
        $this->post(route('master.pengguna.tambah'), ['nip' => $joko['nip'], 'peran' => 'satker'])->assertSessionHasErrors('peran');
        $this->assertNull(User::where('nip', $joko['nip'])->first());
    }

    public function test_dti_dan_admin_hanya_diatur_admin(): void
    {
        $dti = User::where('email', 'dti@contoh.test')->firstOrFail();
        $this->masuk('setba@contoh.test')->get(route('master', ['tab' => 'pengguna']))->assertForbidden();
        $this->post(route('master.pengguna.saklar', $dti))->assertForbidden();
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
        $admin = User::where('email', 'admin@contoh.test')->firstOrFail();
        $saya = User::where('email', 'setba@contoh.test')->firstOrFail();
        $this->post(route('master.pengguna.saklar', $admin))
            ->assertSessionHas('gagal', fn ($g) => str_contains($g, 'Akun Anda sendiri'));
        $this->post(route('master.pengguna.simpan', $admin), ['peran' => 'uki'])
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

    public function test_izin_masuk_satker_terpisah_dari_unit_dan_pergantian_peran(): void
    {
        $unit = Satker::where('kode', 'BALAI-4-BANDUNG')->firstOrFail();
        $pj = $unit->penanggungJawab;
        $token = $pj->remember_token;
        $this->post(route('master.pengguna.saklar', $pj))
            ->assertSessionHas('pesan');
        $this->assertFalse($pj->fresh()->aktif);
        $this->assertTrue($unit->fresh()->aktif);
        $this->assertSame($unit->id, $pj->fresh()->satker_id);
        $this->assertNotSame($token, $pj->fresh()->remember_token);
        $this->actingAs($pj->fresh())->get(route('rekomendasi.index'))->assertRedirect(route('masuk'))
            ->assertSessionHasErrors(['email' => 'Izin masuk akun ini sudah dinonaktifkan. Hubungi Admin DTI untuk informasi atau pengaktifan kembali.']);
        $this->masuk('admin@contoh.test')->post(route('master.pengguna.saklar', $pj))->assertSessionHas('pesan');
        $this->assertTrue($pj->fresh()->aktif);
        $this->post(route('master.pengguna.simpan', $pj), ['peran' => 'uki'])
            ->assertSessionHas('gagal', fn ($g) => str_contains($g, 'lewat tab Satuan kerja'));
        $this->assertSame(PeranPengguna::SATKER, $pj->fresh()->peran);
        $this->assertSame($unit->id, $pj->fresh()->satker_id);
    }

    public function test_petugas_pusat_bisa_sekaligus_jadi_penanggung_jawab_dengan_identitas_yang_sama(): void
    {
        $bandung = Satker::where('kode', 'BALAI-4-BANDUNG')->firstOrFail();
        $sri = $this->pegawai('Sri Wahyuni');
        $id = User::where('nip', $sri['nip'])->value('id');
        $this->get(route('master', ['pj' => $bandung->id, 'q' => 'Sri Wahyuni']))->assertOk()
            ->assertSee('Tambahkan akses Satuan kerja');
        $this->post(route('master.unit.pj', $bandung), ['nip' => $sri['nip']])
            ->assertSessionHas('pesan');
        $pj = $bandung->fresh()->penanggungJawab;
        $this->assertSame($id, $pj->id);
        $this->assertTrue($pj->punyaPeran(PeranPengguna::SETBA));
        $this->assertSame(PeranPengguna::SATKER, $pj->peran);
        $this->assertSame(1, User::where('nip', $sri['nip'])->count());
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

    public function test_pj_lama_tidak_bisa_diaktifkan_saat_unit_sudah_dipegang_orang_lain(): void
    {
        $unit = Satker::where('kode', 'BALAI-4-BANDUNG')->firstOrFail();
        $lama = $unit->penanggungJawab;
        $rina = $this->pegawai('Rina Wulandari');
        $this->post(route('master.unit.pj', $unit), ['nip' => $rina['nip']])->assertRedirect();
        $baru = $unit->fresh()->penanggungJawab;
        $this->post(route('master.pengguna.saklar', $lama))
            ->assertSessionHas('gagal', fn ($g) => str_contains($g, 'sudah memiliki penanggung jawab aktif'));
        $this->post(route('master.pengguna.massal'), ['aksi' => 'aktif', 'id' => [$lama->id]])
            ->assertSessionHas('gagal', fn ($g) => str_contains($g, 'sudah memiliki penanggung jawab aktif'));
        $this->assertFalse($lama->fresh()->aktif);
        $this->assertSame($baru->id, $unit->fresh()->penanggungJawab->id);
    }

    public function test_unit_nonaktif_tetap_bisa_memiliki_izin_masuk_untuk_pekerjaan_lama(): void
    {
        $unit = Satker::where('kode', 'BALAI-4-BANDUNG')->firstOrFail();
        $pj = $unit->penanggungJawab;
        $this->post(route('master.unit.saklar', $unit))->assertRedirect();
        $this->assertFalse($unit->fresh()->aktif);
        $this->assertTrue($pj->fresh()->aktif);
        $this->post(route('master.pengguna.massal'), ['aksi' => 'nonaktif', 'id' => [$pj->id]])->assertSessionHas('pesan');
        $this->assertFalse($pj->fresh()->aktif);
        $this->post(route('master.pengguna.massal'), ['aksi' => 'aktif', 'id' => [$pj->id]])->assertSessionHas('pesan');
        $this->assertTrue($pj->fresh()->aktif);
        $this->assertFalse($unit->fresh()->aktif);
    }

    public function test_admin_bisa_mengakhiri_sesi_satker_tanpa_mencabut_izin(): void
    {
        config(['session.driver' => 'database']);
        $pj = $this->akun('bandung');
        $token = $pj->remember_token;
        DB::table('sessions')->insert(['id' => 'uji-perangkat-pj', 'user_id' => $pj->id,
            'payload' => base64_encode(serialize([])), 'last_activity' => now()->getTimestamp()]);
        $this->post(route('master.pengguna.sesi', $pj))->assertSessionHas('pesan');
        $this->assertDatabaseMissing('sessions', ['id' => 'uji-perangkat-pj']);
        $this->assertTrue($pj->fresh()->aktif);
        $this->assertNotSame($token, $pj->fresh()->remember_token);

        DB::table('sessions')->insert(['id' => 'uji-perangkat-pj-massal', 'user_id' => $pj->id,
            'payload' => base64_encode(serialize([])), 'last_activity' => now()->getTimestamp()]);
        $this->post(route('master.pengguna.massal'), ['aksi' => 'sesi', 'id' => [$pj->id]])->assertSessionHas('pesan');
        $this->assertDatabaseMissing('sessions', ['id' => 'uji-perangkat-pj-massal']);
        $this->assertTrue($pj->fresh()->aktif);
    }

    public function test_penonaktifan_satker_memutus_seluruh_perangkat_dan_tidak_mengganti_unit(): void
    {
        config(['session.driver' => 'database']);
        $pj = $this->akun('bandung');
        $unit = $pj->satker_id;
        foreach (['uji-pj-1', 'uji-pj-2'] as $id) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $pj->id,
                'payload' => base64_encode(serialize([])), 'last_activity' => now()->getTimestamp()]);
        }
        $this->post(route('master.pengguna.saklar', $pj))->assertSessionHas('pesan');
        $this->assertSame(0, DB::table('sessions')->where('user_id', $pj->id)->count());
        $this->assertFalse($pj->fresh()->aktif);
        $this->assertSame($unit, $pj->fresh()->satker_id);
        $log = LogAktivitas::where('aksi', 'master.pengguna.saklar')->latest('id')->firstOrFail();
        $this->assertSame(['aktif' => false], $log->rincian['sesudah']);
    }

    public function test_tambah_pengguna_tidak_mengubah_akun_satker_nonaktif_jadi_petugas_pusat(): void
    {
        $pj = $this->akun('bandung');
        $this->post(route('master.pengguna.saklar', $pj))->assertSessionHas('pesan');
        $this->post(route('master.pengguna.tambah'), ['nip' => $pj->nip, 'peran' => 'uki'])
            ->assertSessionHas('gagal', fn ($g) => str_contains($g, 'Akun satuan kerja sudah terdaftar'));
        $this->post(route('master.pengguna.banyak'), ['nip' => $pj->nip, 'peran' => 'uki'])->assertSessionHas('gagal');
        $this->assertFalse($pj->fresh()->aktif);
        $this->assertSame(PeranPengguna::SATKER, $pj->fresh()->peran);
    }

    public function test_tindakan_massal_menyisakan_setba_terakhir_dan_akun_admin_sendiri(): void
    {
        $setba = $this->akun('setba@contoh.test');
        $cadangan = User::factory()->create(['peran' => PeranPengguna::SETBA, 'aktif' => true]);
        $admin = $this->akun('admin@contoh.test');
        $this->post(route('master.pengguna.massal'), ['aksi' => 'nonaktif', 'id' => [$setba->id, $cadangan->id, $admin->id]])
            ->assertSessionHas('pesan');
        $this->assertSame(1, User::where('peran', 'setba')->where('aktif', true)->count());
        $this->assertTrue($admin->fresh()->aktif);
    }

    public function test_izin_pelaku_yang_sudah_berubah_tidak_dipercaya_dari_model_sesi_lama(): void
    {
        $admin = $this->akun('admin@contoh.test');
        $target = $this->akun('dti@contoh.test');
        User::whereKey($admin->id)->update(['peran' => PeranPengguna::UKI->value]);
        $this->actingAs($admin)->post(route('master.pengguna.saklar', $target))->assertForbidden();
        $this->assertTrue($target->fresh()->aktif);
        $siti = $this->pegawai('Siti Rahmawati');
        $this->actingAs($admin)->post(route('master.pengguna.tambah'), ['nip' => $siti['nip'], 'peran' => 'uki'])->assertForbidden();
        $this->assertDatabaseMissing('users', ['nip' => $siti['nip']]);
    }

    public function test_akun_satker_tanpa_unit_tidak_bisa_diaktifkan(): void
    {
        $u = User::factory()->create(['peran' => PeranPengguna::SATKER, 'satker_id' => null, 'aktif' => false]);
        $this->post(route('master.pengguna.saklar', $u))
            ->assertSessionHas('gagal', fn ($g) => str_contains($g, 'belum terhubung ke unit'));
        $this->assertFalse($u->fresh()->aktif);
    }

    public function test_izin_satker_hanya_dikelola_admin_dti(): void
    {
        $pj = $this->akun('bandung');
        foreach (['setba@contoh.test', 'dti@contoh.test', 'uki@contoh.test'] as $email) {
            $this->masuk($email)->post(route('master.pengguna.saklar', $pj))->assertForbidden();
            $this->post(route('master.pengguna.massal'), ['aksi' => 'nonaktif', 'id' => [$pj->id]])->assertForbidden();
            $this->post(route('master.pengguna.sesi', $pj))->assertForbidden();
        }
        $this->assertTrue($pj->fresh()->aktif);
    }
}
