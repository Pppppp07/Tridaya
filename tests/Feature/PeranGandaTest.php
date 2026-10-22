<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna as P;
use App\Enums\PosisiBerkas;
use App\Models\AlertKeamanan;
use App\Models\LogAktivitas;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Models\Satker;
use App\Models\User;
use App\Notifications\PemberitahuanEmail;
use App\Support\Akun;
use App\Support\DirektoriIrm;
use App\Support\Pemberitahuan;
use App\Support\Terlihat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\PakaiDataContoh;
use Tests\TestCase;

class PeranGandaTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        $this->masuk('admin@contoh.test');
    }

    private function beri(User $user, array $perans)
    {
        return $this->post(route('master.pengguna.simpan', $user), ['perans_dikirim' => 1, 'perans' => $perans]);
    }

    private function pilih(string $peran)
    {
        return $this->post(route('akun.peran-aktif'), ['peran' => $peran]);
    }

    public function test_admin_memberi_dua_peran_tanpa_menduplikasi_identitas(): void
    {
        $uki = $this->akun('uki@contoh.test');
        $jumlah = User::count();
        $versi = $uki->versi_akses;
        $this->beri($uki, ['uki', 'inspektorat'])->assertRedirect()->assertSessionHas('pesan');
        $baru = $uki->fresh();
        $this->assertSame(P::UKI, $baru->peran);
        $this->assertSame(['inspektorat'], $baru->peran_tambahan);
        $this->assertSame($versi + 1, $baru->versi_akses);
        $this->assertSame($jumlah, User::count());
        $this->assertSame(1, User::where('nip', $uki->nip)->count());
        $log = LogAktivitas::where('aksi', 'master.pengguna.peran')->latest('id')->firstOrFail();
        $this->assertSame(['uki'], $log->rincian['sebelum']['perans']);
        $this->assertSame(['inspektorat', 'uki'], $log->rincian['sesudah']['perans']);
        $this->assertSame('admin', $log->peran);
        // Pengurutan checkbox saja tidak memutus sesi atau mencatat perubahan palsu.
        $this->beri($baru, ['inspektorat', 'uki'])->assertSessionHas('pesan');
        $this->assertSame($versi + 1, $baru->fresh()->versi_akses);
        $this->assertSame(1, LogAktivitas::where('aksi', 'master.pengguna.peran')->count());
    }

    public function test_admin_menambah_user_dengan_beberapa_peran_dari_satu_nip(): void
    {
        $pegawai = DirektoriIrm::semua()->firstWhere('nama', 'Siti Rahmawati');
        $this->post(route('master.pengguna.tambah'), ['nip' => $pegawai['nip'], 'perans' => ['uki', 'inspektorat']])
            ->assertRedirect()->assertSessionHas('pesan');
        $user = User::where('nip', $pegawai['nip'])->firstOrFail();
        $this->assertSame($pegawai['email'], $user->email);
        $this->assertSame([P::UKI, P::INSPEKTORAT], $user->semuaPeran());
        $this->post(route('master.pengguna.tambah'), ['nip' => $pegawai['nip'], 'perans' => ['admin']])
            ->assertSessionHas('gagal');
        $this->assertSame(1, User::where('nip', $pegawai['nip'])->count());
        $this->assertFalse($user->fresh()->punyaPeran(P::ADMIN));
    }

    public function test_peran_aktif_mengatur_hak_tanpa_menggabungkan_peran_lain(): void
    {
        $uki = $this->akun('uki@contoh.test');
        $this->beri($uki, ['uki', 'admin'])->assertSessionHas('pesan');
        $this->actingAs($uki->fresh())->get(route('master'))->assertForbidden();
        $token = session()->token();
        $this->pilih('admin')->assertRedirect(route('beranda'))->assertSessionHas('akses_aktif.peran', 'admin');
        $this->assertNotSame($token, session()->token(), 'Form lama harus kehilangan CSRF token setelah ganti peran.');
        $this->get(route('master'))->assertOk();
        $this->beri($this->akun('dti@contoh.test'), ['dti', 'inspektorat'])->assertSessionHas('pesan');
        $log = LogAktivitas::where('aksi', 'master.pengguna.peran')->latest('id')->firstOrFail();
        $this->assertSame([$uki->id, 'admin'], [$log->user_id, $log->peran]);
        $this->pilih('uki')->assertRedirect();
        $this->get(route('master'))->assertForbidden();
        $this->assertSame(P::UKI, $uki->fresh()->peran);
    }

    public function test_uki_dan_itjen_memutus_dengan_peran_aktif_yang_sesuai(): void
    {
        $uki = $this->akun('uki@contoh.test');
        $this->beri($uki, ['uki', 'inspektorat'])->assertSessionHas('pesan');
        $baris = Sasaran::all()->first(fn ($s) => $s->pos() === PosisiBerkas::INSPEKTORAT);
        $this->assertNotNull($baris);
        $data = ['hasil' => 'M', 'nomor' => 'CHV/PERAN-GANDA/01', 'tgl_surat' => now()->toDateString(), 'catatan' => 'Bukti telah diperiksa.'];
        $this->actingAs($uki->fresh())->post(route('sasaran.putus', $baris), $data)->assertForbidden();
        $this->assertSame(PosisiBerkas::INSPEKTORAT, $baris->fresh()->pos());
        $this->pilih('inspektorat')->assertRedirect();
        $this->post(route('sasaran.putus', $baris), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNotSame(PosisiBerkas::INSPEKTORAT, $baris->fresh()->pos());
        $log = LogAktivitas::where('user_id', $uki->id)->where('peran', 'inspektorat')->latest('id')->firstOrFail();
        $this->assertNotSame('akun.peran_aktif', $log->aksi);
        $this->assertSame(P::UKI, $uki->fresh()->peran);
        $alih = LogAktivitas::where('aksi', 'akun.peran_aktif')->latest('id')->firstOrFail();
        $this->assertSame('uki', $alih->peran);
        $this->assertSame('inspektorat', $alih->rincian['sesudah']['peran_aktif']);
    }

    public function test_peran_hanya_melihat_dapat_beralih_tanpa_membawa_hak_lamanya(): void
    {
        $dti = $this->akun('dti@contoh.test');
        $this->beri($dti, ['dti', 'inspektorat'])->assertSessionHas('pesan');
        $this->actingAs($dti->fresh())->get(route('log'))->assertOk();
        $this->pilih('inspektorat')->assertRedirect();
        $this->get(route('log'))->assertForbidden();
        $this->pilih('dti')->assertRedirect();
        $this->get(route('log'))->assertOk();
    }

    public function test_peran_tidak_diberikan_duplikat_dan_perubahan_diri_ditolak(): void
    {
        $uki = $this->akun('uki@contoh.test');
        $this->beri($uki, ['uki', 'uki'])->assertSessionHasErrors('perans.0');
        $this->beri($uki, ['uki', 'satker'])->assertSessionHasErrors('perans.1');
        $this->beri($this->akun('admin@contoh.test'), ['admin', 'uki'])->assertSessionHas('gagal');
        $this->beri($uki, [])->assertSessionHas('gagal');
        $this->actingAs($uki->fresh());
        $this->pilih('admin')->assertSessionHasErrors('peran');
        $this->beri($this->akun('dti@contoh.test'), ['admin'])->assertForbidden();
        $this->assertSame([P::UKI], $uki->fresh()->semuaPeran());
    }

    public function test_sesi_lama_berakhir_setelah_peran_dicabut_meski_sesi_bukan_database(): void
    {
        $uki = $this->akun('uki@contoh.test');
        $this->beri($uki, ['uki', 'inspektorat'])->assertSessionHas('pesan');
        $lama = $uki->fresh();
        $this->beri($lama, ['uki'])->assertSessionHas('pesan');
        $this->actingAs($lama->fresh())->withSession([
            'izin_akun' => ['user' => $lama->id, 'versi' => $lama->versi_akses],
            'akses_aktif' => ['user' => $lama->id, 'peran' => 'inspektorat'],
        ])->get(route('rekomendasi.index'))->assertRedirect(route('masuk'))
            ->assertSessionHasErrors(['email' => 'Hak akses Anda berubah. Silakan masuk ulang.']);
        $this->assertGuest();
        $this->assertFalse($lama->fresh()->punyaPeran(P::INSPEKTORAT));
    }

    public function test_setba_terakhir_dilindungi_meski_hanya_peran_tambahan(): void
    {
        $setba = $this->akun('setba@contoh.test');
        $setba->forceFill(['peran' => 'uki', 'peran_tambahan' => ['setba']])->save();
        $this->beri($setba, ['uki'])->assertSessionHas('gagal', fn ($s) => str_contains($s, 'Setba'));
        $this->post(route('master.pengguna.saklar', $setba))->assertSessionHas('gagal');
        $this->beri($setba, ['uki', 'setba', 'inspektorat'])->assertSessionHas('pesan');
        $this->assertTrue($setba->fresh()->punyaPeran(P::SETBA));
        $this->assertTrue($setba->fresh()->aktif);
    }

    public function test_pencabutan_sesi_dengan_dua_snapshot_user_tidak_kehilangan_versi_akses(): void
    {
        $pertama = $this->akun('uki@contoh.test');
        $kedua = $pertama->fresh();
        $versi = $pertama->versi_akses;
        Akun::putuskanSesi($pertama);
        Akun::putuskanSesi($kedua);
        $this->assertSame($versi + 2, $pertama->fresh()->versi_akses);
        $this->assertNotSame($pertama->remember_token, $kedua->remember_token);
    }

    public function test_filter_jumlah_dan_ekspor_master_memuat_peran_tambahan(): void
    {
        $uki = $this->akun('uki@contoh.test');
        $this->beri($uki, ['uki', 'inspektorat'])->assertSessionHas('pesan');
        $this->get(route('master', ['tab' => 'pengguna', 'peran' => 'inspektorat']))->assertOk()->assertSee($uki->name);
        $this->get(route('master', ['tab' => 'peran']))->assertOk()->assertViewHas('perAkun', fn ($p) => $p['inspektorat'] === 2);
        $csv = $this->get(route('master.pengguna.unduh', ['peran' => 'inspektorat']))->assertOk()->streamedContent();
        $this->assertStringContainsString($uki->name, $csv);
        $this->assertStringContainsString('UKI, Inspektorat', $csv);
    }

    public function test_satker_dan_peran_pusat_tetap_memiliki_cakupan_terpisah(): void
    {
        $satker = $this->akun('medan');
        $this->beri($satker, ['uki'])->assertSessionHas('pesan');
        $this->actingAs($satker->fresh())->get(route('rekomendasi.index'))->assertOk();
        $unit = $satker->satker_id;
        $jumlahUnit = Terlihat::untuk($satker->fresh())->rekomendasi()->count();
        $this->assertLessThan(Rekomendasi::count(), $jumlahUnit);
        $this->pilih('uki')->assertRedirect();
        $this->assertSame(Rekomendasi::count(), Terlihat::untuk($satker->fresh())->rekomendasi()->count());
        $this->pilih('satker')->assertRedirect();
        $this->assertSame($jumlahUnit, Terlihat::untuk($satker->fresh())->rekomendasi()->count());
        $this->assertSame($unit, $satker->fresh()->satker_id);
    }

    public function test_ganti_pj_mencabut_satker_saja_dan_mempertahankan_peran_pusat(): void
    {
        $bandung = Satker::where('kode', 'BALAI-4-BANDUNG')->firstOrFail();
        $lama = $bandung->penanggungJawab;
        $this->beri($lama, ['uki', 'inspektorat'])->assertSessionHas('pesan');
        $baru = $this->akun('setba@contoh.test');
        $this->post(route('master.unit.pj', $bandung), ['nip' => $baru->nip])->assertSessionHas('pesan');
        $this->assertSame($baru->id, $bandung->fresh()->penanggungJawab->id);
        $this->assertSame([P::SATKER, P::SETBA], $baru->fresh()->semuaPeran());
        $this->assertSame([P::UKI, P::INSPEKTORAT], $lama->fresh()->semuaPeran());
        $this->assertNull($lama->fresh()->satker_id);
        $this->assertTrue($lama->fresh()->aktif);
        $versi = $baru->fresh()->versi_akses;
        $this->post(route('master.unit.pj', $bandung), ['nip' => $baru->nip])->assertSessionHas('pesan');
        $this->assertSame($versi, $baru->fresh()->versi_akses, 'Menetapkan ulang PJ yang sama bukan perubahan izin.');
    }

    public function test_email_tidak_ganda_untuk_user_yang_memenuhi_dua_peran_penerima(): void
    {
        Notification::fake();
        $pj = $this->akun('medan');
        $this->beri($pj, ['uki'])->assertSessionHas('pesan');
        $rek = Rekomendasi::firstOrFail();
        $notifikasi = Pemberitahuan::tulis($rek, 'Berkas dikirim untuk diperiksa', [P::SATKER, P::UKI], null, [$pj->satker_id]);
        $this->app->terminate();
        Notification::assertSentToTimes($pj, PemberitahuanEmail::class, 1);
        Notification::assertSentTo($pj, PemberitahuanEmail::class, fn ($n) => $n->pemberitahuan->is($notifikasi));
    }

    public function test_pj_lama_yang_nonaktif_bisa_kembali_dengan_peran_pusat_tanpa_merebut_unit(): void
    {
        $bandung = Satker::where('kode', 'BALAI-4-BANDUNG')->firstOrFail();
        $lama = $bandung->penanggungJawab;
        $pegawai = DirektoriIrm::semua()->firstWhere('nama', 'Rina Wulandari');
        $this->post(route('master.unit.pj', $bandung), ['nip' => $pegawai['nip']])->assertSessionHas('pesan');
        $this->assertFalse($lama->fresh()->aktif);
        $this->beri($lama->fresh(), ['uki', 'inspektorat'])->assertSessionHas('pesan');
        $this->post(route('master.pengguna.saklar', $lama))->assertSessionHas('pesan');
        $this->assertSame([P::UKI, P::INSPEKTORAT], $lama->fresh()->semuaPeran());
        $this->assertNull($lama->fresh()->satker_id);
        $this->assertTrue($lama->fresh()->aktif);
        $this->assertSame($pegawai['nip'], $bandung->fresh()->penanggungJawab->nip);
    }

    public function test_email_peran_pusat_tidak_memakai_cakupan_unit_yang_tidak_dituju(): void
    {
        $pj = $this->akun('medan');
        $this->beri($pj, ['uki'])->assertSessionHas('pesan');
        $rek = Rekomendasi::firstOrFail();
        $notifikasi = Pemberitahuan::tulis($rek, 'Berkas Balai Wil. IV Bandung perlu ditelaah', [P::UKI]);
        $mail = (new PemberitahuanEmail($notifikasi))->toMail($pj->fresh());
        $this->assertStringContainsString('Balai Wil. IV Bandung', $mail->introLines[0]);
        $this->assertStringNotContainsString('penanggung jawab', $mail->salutation);
    }

    public function test_alert_memeriksa_peran_penting_di_dalam_kombinasi(): void
    {
        DB::table('pemantauan_keamanan')->where('id', 1)->update(['mulai_pada' => now()]);
        $uki = $this->akun('uki@contoh.test');
        $this->beri($uki, ['uki', 'admin'])->assertSessionHas('pesan');
        $alert = AlertKeamanan::where('aturan', 'perubahan_peran')->firstOrFail();
        $this->assertSame('tinggi', $alert->tingkat);
        $this->assertSame(['admin', 'uki'], $alert->bukti['peran_sesudah']);
        $this->post(route('master.pengguna.saklar', $uki))->assertSessionHas('pesan');
        $alert = AlertKeamanan::where('aturan', 'izin_peran_penting_berubah')->firstOrFail();
        $this->assertSame('admin', $alert->bukti['peran_target']);
    }

    public function test_menu_akun_menampilkan_peran_aktif_dan_semua_pilihan_yang_diberikan(): void
    {
        $uki = $this->akun('uki@contoh.test');
        $uki->forceFill(['pengaturan' => ['tema' => 'gelap']])->save();
        $this->beri($uki, ['uki', 'inspektorat'])->assertSessionHas('pesan');
        $this->actingAs($uki->fresh())->get(route('akun'))->assertOk()->assertSee('Peran aktif')
            ->assertSee('action="'.route('akun.peran-aktif').'"', false)
            ->assertSee('value="inspektorat"', false);
        $this->pilih('inspektorat')->assertRedirect();
        $this->get(route('akun'))->assertOk()->assertSee('Inspektorat')->assertSee('data-tema="gelap"', false);
        $this->assertSame(P::UKI, $uki->fresh()->peran);
        // Pilihan di browser lain tidak mengubah peran bawaan atau sesi ini.
        $this->withSession(['akses_aktif' => ['user' => $uki->id + 1000, 'peran' => 'admin']]);
        $this->get(route('master'))->assertForbidden();
    }
}
