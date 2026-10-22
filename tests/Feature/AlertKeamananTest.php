<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\AlertKeamanan;
use App\Models\LogAktivitas;
use App\Models\TinjauanAlert;
use App\Models\User;
use App\Support\Aktivitas;
use App\Support\AlertKeamanan as Pemantauan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AlertKeamananTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-01 09:00:00');
        DB::table('pemantauan_keamanan')->where('id', 1)->update(['mulai_pada' => now()]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function peristiwa(string $aksi, array $isi = [], array $rincian = []): LogAktivitas
    {
        $log = LogAktivitas::create($isi + [
            'waktu' => now(), 'aksi' => $aksi, 'kelompok' => Aktivitas::kelompokDari($aksi),
            'ringkasan' => 'Fakta untuk pengujian', 'ip' => '192.0.2.10', 'nama' => 'Pegawai',
            'rincian' => $rincian + ['konteks' => ['sumber' => 'web']],
        ]);
        Pemantauan::proses($log);

        return $log;
    }

    public function test_gagal_masuk_berulang_dikelompokkan_dengan_ambang_dan_bukti(): void
    {
        $ids = [];
        for ($i = 0; $i < 4; $i++) {
            $ids[] = $this->peristiwa('masuk.gagal')->id;
        }
        $this->assertDatabaseCount('alert_keamanan', 0);
        $ids[] = $this->peristiwa('masuk.gagal')->id;
        $alert = AlertKeamanan::firstOrFail();
        $this->assertSame(['masuk_gagal_berulang', 'tinggi', 'baru'], [$alert->aturan, $alert->tingkat, $alert->status]);
        $this->assertSame($ids, $alert->bukti['log_ids']);
        $this->assertSame(5, $alert->bukti['jumlah']);
        $this->assertSame(10, $alert->bukti['jendela_menit']);
        $this->peristiwa('masuk.gagal');
        $this->assertDatabaseCount('alert_keamanan', 1);
        $this->assertSame(6, $alert->fresh()->bukti['jumlah']);
        $this->assertCount(6, $alert->fresh()->logBukti());
        $this->assertStringContainsString('IP dapat dipakai bersama', $alert->alasan);
    }

    public function test_link_ditolak_berulang_per_user_menjadi_alert_dengan_bukti_yang_bisa_ditinjau(): void
    {
        $u = User::factory()->create(['peran' => 'satker', 'aktif' => true]);
        $v = User::factory()->create(['peran' => 'uki', 'aktif' => true]);
        $ids = [];
        for ($i = 0; $i < 4; $i++) {
            $ids[] = $this->peristiwa('bukti.link.ditolak', ['user_id' => $u->id], ['domain' => 'evil.test', 'alasan' => 'sumber'])->id;
        }
        $this->peristiwa('bukti.link.ditolak', ['user_id' => $v->id]);
        $this->assertDatabaseCount('alert_keamanan', 0);
        $ids[] = $this->peristiwa('bukti.link.ditolak', ['user_id' => $u->id])->id;
        $alert = AlertKeamanan::firstOrFail();
        $this->assertSame('link_bukti_ditolak_berulang', $alert->aturan);
        $this->assertSame('perhatian', $alert->tingkat);
        $this->assertSame($u->id, $alert->user_id);
        $this->assertSame($ids, $alert->bukti['log_ids']);
        $this->assertStringContainsString('tidak otomatis berarti pelanggaran', $alert->alasan);
    }

    public function test_ambang_tidak_menggabungkan_ip_atau_waktu_di_luar_jendela(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->peristiwa('masuk.gagal');
        }
        $this->peristiwa('masuk.gagal', ['ip' => '192.0.2.11']);
        $this->assertDatabaseCount('alert_keamanan', 0);
        Carbon::setTestNow(now()->addMinutes(11));
        $this->peristiwa('masuk.gagal');
        $this->assertDatabaseCount('alert_keamanan', 0);
    }

    public function test_penolakan_per_akun_dan_pembatasan_masuk_memakai_fakta_tersendiri(): void
    {
        $u = User::factory()->create(['peran' => 'dti', 'aktif' => true]);
        $v = User::factory()->create(['peran' => 'uki', 'aktif' => true]);
        $this->peristiwa('akses.ditolak', ['user_id' => $u->id]);
        $this->peristiwa('akses.ditolak', ['user_id' => $u->id]);
        $this->peristiwa('akses.ditolak', ['user_id' => $v->id]);
        $this->assertDatabaseCount('alert_keamanan', 0);
        $this->peristiwa('akses.ditolak', ['user_id' => $u->id]);
        $this->assertSame($u->id, AlertKeamanan::firstOrFail()->user_id);
        $this->peristiwa('masuk.dikunci');
        $this->peristiwa('masuk.dikunci');
        $this->assertDatabaseCount('alert_keamanan', 2);
        $this->assertSame(2, AlertKeamanan::where('aturan', 'masuk_dikunci')->firstOrFail()->bukti['jumlah']);
    }

    public function test_perubahan_peran_dan_pemberian_admin_ditinjau_tanpa_tuduhan(): void
    {
        $this->peristiwa('master.pengguna.peran', [], ['sebelum' => ['peran' => 'uki'], 'sesudah' => ['peran' => 'dti']]);
        $log = $this->peristiwa('master.pengguna.tambah', [], ['sebelum' => [], 'sesudah' => ['peran' => 'admin']]);
        Pemantauan::proses($log);
        $this->assertDatabaseCount('alert_keamanan', 2);
        $this->assertSame('perhatian', AlertKeamanan::where('aturan', 'perubahan_peran')->firstOrFail()->tingkat);
        $admin = AlertKeamanan::where('aturan', 'peran_penting_diberikan')->firstOrFail();
        $this->assertSame('tinggi', $admin->tingkat);
        $this->assertSame('admin', $admin->bukti['peran_sesudah']);
        $this->assertStringContainsString('administrasi resmi', $admin->alasan);
    }

    public function test_aktivitas_biasa_tidak_dijadikan_ancaman_dan_log_lama_tidak_disapu(): void
    {
        foreach (['masuk', 'masuk.sso.ditolak', 'akses.akun_nonaktif', 'akses.dibatasi', 'master.kategori.saklar'] as $aksi) {
            $this->peristiwa($aksi);
        }
        $this->peristiwa('master.pengguna.peran', [], ['sebelum' => ['peran' => 'uki'], 'sesudah' => ['peran' => 'uki']]);
        $this->peristiwa('master.pengguna.tambah', [], ['sebelum' => [], 'sesudah' => ['peran' => 'uki']]);
        for ($i = 0; $i < 5; $i++) {
            $this->peristiwa('masuk.gagal', ['waktu' => now()->subMinute()]);
        }
        $this->assertDatabaseCount('alert_keamanan', 0);
        $this->assertTrue(Pemantauan::mulaiPada()->equalTo(now()));
    }

    public function test_perintah_server_diberi_konteks_bukan_alamat_semu(): void
    {
        $log = Aktivitas::catat('master.unit.tambah', 'Administrasi server', ['oleh' => null, 'nama' => 'Perintah server']);
        $this->assertNull($log->ip);
        $this->assertNull($log->agen);
        $this->assertSame(['sumber' => 'perintah_server'], $log->rincian['konteks']);
        $alert = AlertKeamanan::firstOrFail();
        $this->assertSame('perintah_server', $alert->sumber);
        $this->assertStringContainsString('pemeliharaan resmi', $alert->alasan);
    }

    public function test_konteks_http_hanya_menyimpan_path_metode_dan_rute_tanpa_rahasia(): void
    {
        Route::post('/uji-jejak', function () {
            Aktivitas::catat('akun.uji', 'Aktivitas HTTP');

            return response('ok');
        })->name('uji.jejak');
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])->post('/uji-jejak?token=rahasia-query', ['password' => 'rahasia-body'])->assertOk();
        $log = LogAktivitas::firstOrFail();
        $this->assertSame(['sumber' => 'web', 'metode' => 'POST', 'alamat' => '/uji-jejak', 'rute' => 'uji.jejak'], $log->rincian['konteks']);
        $this->assertSame('192.0.2.20', $log->ip);
        $this->assertStringNotContainsString('rahasia', json_encode($log->toArray()));
    }

    public function test_admin_meninjau_dan_keputusan_lama_tetap_tersimpan(): void
    {
        $admin = User::factory()->create(['name' => 'Admin Penguji', 'peran' => 'admin', 'aktif' => true]);
        $log = $this->peristiwa('masuk.dikunci');
        $asli = $log->fresh()->getRawOriginal();
        $alert = AlertKeamanan::firstOrFail();
        $this->actingAs($admin)->post(route('log.alert.tinjau', $alert), ['keputusan' => 'wajar', 'catatan' => 'Pengguna mengonfirmasi salah mengetik password.', 'bukti_sampai' => $log->id])->assertRedirect();
        $this->assertSame('wajar', $alert->fresh()->status);
        $this->assertNull($alert->fresh()->kunci_aktif);
        $this->assertSame($asli, $log->fresh()->getRawOriginal());
        $this->post(route('log.alert.tinjau', $alert), ['keputusan' => 'selesai', 'catatan' => 'Tinjauan diselesaikan setelah konfirmasi.', 'bukti_sampai' => $log->id])->assertRedirect();
        $this->assertDatabaseCount('tinjauan_alert', 2);
        $this->assertSame(['wajar', 'selesai'], TinjauanAlert::orderBy('id')->pluck('keputusan')->all());
        $this->assertSame('Admin Penguji', TinjauanAlert::firstOrFail()->nama);
        $this->assertSame('selesai', $alert->fresh()->status);
        $this->assertSame(2, LogAktivitas::where('aksi', 'keamanan.alert.tinjau')->count());
    }

    public function test_bukti_setelah_tinjauan_membuat_alert_baru_tanpa_memakai_ulang_bukti_lama(): void
    {
        $admin = User::factory()->create(['peran' => 'admin', 'aktif' => true]);
        for ($i = 0; $i < 5; $i++) {
            $this->peristiwa('masuk.gagal');
        }
        $lama = AlertKeamanan::firstOrFail();
        $this->actingAs($admin)->post(route('log.alert.tinjau', $lama), ['keputusan' => 'perlu_tindak_lanjut', 'catatan' => 'Periksa bersama administrator jaringan.', 'bukti_sampai' => $lama->bukti['sampai_log_id']])->assertRedirect();
        $ids = [];
        for ($i = 0; $i < 4; $i++) {
            $ids[] = $this->peristiwa('masuk.gagal')->id;
        }
        $this->assertDatabaseCount('alert_keamanan', 1);
        $ids[] = $this->peristiwa('masuk.gagal')->id;
        $this->assertDatabaseCount('alert_keamanan', 2);
        $baru = AlertKeamanan::latest('id')->firstOrFail();
        $this->assertSame($ids, $baru->bukti['log_ids']);
        $this->assertSame('baru', $baru->status);
        $this->assertSame('perlu_tindak_lanjut', $lama->fresh()->status);
    }

    public function test_catatan_wajib_dan_hanya_admin_aktif_boleh_meninjau(): void
    {
        $this->peristiwa('masuk.dikunci');
        $alert = AlertKeamanan::firstOrFail();
        foreach (PeranPengguna::cases() as $peran) {
            if ($peran === PeranPengguna::ADMIN) {
                continue;
            }
            $u = User::factory()->create(['peran' => $peran, 'aktif' => true]);
            $this->actingAs($u)->post(route('log.alert.tinjau', $alert), ['keputusan' => 'wajar', 'catatan' => 'Bukan hak akun ini.', 'bukti_sampai' => $alert->bukti['sampai_log_id']])->assertForbidden();
        }
        $admin = User::factory()->create(['peran' => 'admin', 'aktif' => true]);
        $this->actingAs($admin)->post(route('log.alert.tinjau', $alert), ['keputusan' => 'wajar', 'catatan' => '   ', 'bukti_sampai' => $alert->bukti['sampai_log_id']])->assertSessionHasErrors('catatan');
        $this->post(route('log.alert.tinjau', $alert), ['keputusan' => 'ilegal', 'catatan' => 'Tidak ada pilihan semacam ini.', 'bukti_sampai' => $alert->bukti['sampai_log_id']])->assertSessionHasErrors('keputusan');
        $this->assertDatabaseCount('tinjauan_alert', 0);
        $this->assertSame('baru', $alert->fresh()->status);
    }

    public function test_bukti_dibatasi_tanpa_mengurangi_jumlah_peristiwa(): void
    {
        for ($i = 0; $i < 102; $i++) {
            $this->peristiwa('masuk.gagal');
        }
        $alert = AlertKeamanan::firstOrFail();
        $this->assertSame(102, $alert->bukti['jumlah']);
        $this->assertCount(100, $alert->bukti['log_ids']);
        $this->assertTrue($alert->bukti['bukti_terpotong']);
        $this->assertDatabaseCount('alert_keamanan', 1);
        $this->assertDatabaseCount('alert_keamanan_log', 100);
    }

    public function test_kegagalan_pendeteksi_tidak_menggagalkan_log_asli(): void
    {
        Schema::drop('alert_keamanan_log');
        Schema::drop('tinjauan_alert');
        Schema::drop('alert_keamanan');
        $log = Aktivitas::catat('master.pengguna.tambah', 'Pemberian akun resmi', [
            'oleh' => null, 'rincian' => ['sebelum' => [], 'sesudah' => ['peran' => 'admin']],
        ]);
        $this->assertDatabaseHas('log_aktivitas', ['id' => $log->id, 'aksi' => 'master.pengguna.tambah']);
    }

    public function test_admin_harus_meninjau_bukti_terbaru_sebelum_keputusan_disimpan(): void
    {
        $admin = User::factory()->create(['peran' => 'admin', 'aktif' => true]);
        $log = $this->peristiwa('masuk.dikunci');
        $alert = AlertKeamanan::firstOrFail();
        $baru = $this->peristiwa('masuk.dikunci');
        $this->actingAs($admin)->post(route('log.alert.tinjau', $alert), [
            'keputusan' => 'wajar', 'catatan' => 'Panel lama tidak mencakup bukti baru.', 'bukti_sampai' => $log->id,
        ])->assertSessionHasErrors('bukti_sampai');
        $this->assertDatabaseCount('tinjauan_alert', 0);
        $this->assertSame('baru', $alert->fresh()->status);
        $this->assertSame($baru->id, $alert->fresh()->bukti['sampai_log_id']);
    }

    public function test_retensi_log_tidak_menghapus_snapshot_fakta_dan_tinjauan(): void
    {
        $log = $this->peristiwa('masuk.dikunci');
        $alert = AlertKeamanan::firstOrFail();
        $log->delete();
        $this->assertCount(0, $alert->logBukti());
        $this->assertSame([$log->id], $alert->fresh()->bukti['log_ids']);
        $this->assertSame(1, $alert->fresh()->bukti['jumlah']);
        $this->assertDatabaseCount('alert_keamanan', 1);
    }

    public function test_perubahan_izin_peran_penting_memakai_snapshot_target_dan_prioritas_yang_tepat(): void
    {
        foreach ([['admin', false, true, 'tinggi'], ['admin', true, false, 'perhatian'], ['setba', false, true, 'perhatian'], ['setba', true, false, 'perhatian']] as [$peran, $dari, $ke, $tingkat]) {
            $log = $this->peristiwa('master.pengguna.saklar', ['peran' => 'uki'], [
                'peran_target' => $peran, 'sebelum' => ['aktif' => $dari], 'sesudah' => ['aktif' => $ke],
            ]);
            $alert = AlertKeamanan::latest('id')->firstOrFail();
            $this->assertSame('izin_peran_penting_berubah', $alert->aturan);
            $this->assertSame($tingkat, $alert->tingkat);
            $this->assertSame([$log->id], $alert->bukti['log_ids']);
            $this->assertSame([$peran, $dari, $ke], [$alert->bukti['peran_target'], $alert->bukti['aktif_sebelum'], $alert->bukti['aktif_sesudah']]);
        }
        $this->peristiwa('master.pengguna.saklar', ['peran' => 'admin'], [
            'peran_target' => 'uki', 'sebelum' => ['aktif' => false], 'sesudah' => ['aktif' => true],
        ]);
        $this->peristiwa('master.pengguna.saklar', [], [
            'peran_target' => 'admin', 'sebelum' => ['aktif' => true], 'sesudah' => ['aktif' => true],
        ]);
        $this->assertDatabaseCount('alert_keamanan', 4);
    }

    public function test_reaktivasi_admin_dengan_peran_sama_dicatat_meski_peran_live_sudah_berbeda(): void
    {
        $target = User::factory()->create(['peran' => 'uki', 'aktif' => true]);
        $log = $this->peristiwa('master.pengguna.tambah', ['subjek_tipe' => 'user', 'subjek_id' => $target->id], [
            'sebelum' => ['peran' => 'admin', 'aktif' => false], 'sesudah' => ['peran' => 'admin', 'aktif' => true],
        ]);
        $alert = AlertKeamanan::firstOrFail();
        $this->assertSame('izin_peran_penting_berubah', $alert->aturan);
        $this->assertSame('tinggi', $alert->tingkat);
        $this->assertSame('admin', $alert->bukti['peran_target']);
        $this->assertSame([$log->id], $alert->bukti['log_ids']);
        $this->peristiwa('master.pengguna.tambah', [], [
            'sebelum' => ['peran' => 'uki', 'aktif' => false], 'sesudah' => ['peran' => 'uki', 'aktif' => true],
        ]);
        $this->assertDatabaseCount('alert_keamanan', 1);
    }
}
