<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Models\LogAktivitas;
use App\Models\RiwayatBerkas;
use App\Models\Sasaran;
use App\Models\Satker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Log aktivitas dan akun DTI (27 Sep). Bang Kamal: "setiap aktivitas terekam
 * … DTI itu hanya untuk melihat jika dia merubah data, merusak data, kita
 * tinggal nembak siapa pelakunya … Log aktivitas, sebatas itu. Terus data
 * master enggak … kalau untuk rekomendasi … hanya nge-view doang."
 */
class LogAktivitasTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    public function test_log_awal_menyalin_riwayat_berkas_data_contoh_dengan_pelakunya(): void
    {
        /* Satu baris log per baris riwayat — yang disalin seeder, ditambah yang
           ditulis penyapu draf lewat Jejak. */
        $this->assertSame(RiwayatBerkas::count(), LogAktivitas::where('aksi', 'berkas')->count());

        $rw = RiwayatBerkas::where('label_aktor', 'Balai Wil. I Medan')->orderBy('id')->firstOrFail();
        $log = LogAktivitas::where('aksi', 'berkas')->where('subjek_tipe', 'rekomendasi')
            ->where('subjek_id', $rw->rekomendasi_id)->where('ringkasan', $rw->aksi)->firstOrFail();
        $medan = Satker::where('nama_pendek', 'Balai Wil. I Medan')->firstOrFail();
        $this->assertSame($medan->penanggungJawab->id, $log->user_id);
        $this->assertSame('satker', $log->peran);
        $this->assertSame($medan->id, $log->satker_id);

        $setba = LogAktivitas::where('rincian->atas_nama', 'Setba')->firstOrFail();
        $this->assertSame(User::where('email', 'setba@contoh.test')->value('id'), $setba->user_id);
    }

    public function test_gerak_berkas_tercatat_dengan_kalimat_riwayat_pelaku_dan_alamatnya(): void
    {
        $baris = Sasaran::with('tindakan.rekomendasi.permintaanDokumen.item', 'satker')->get()
            ->first(fn ($x) => $x->pos() === PosisiBerkas::SATKER);
        $pj = User::where('peran', PeranPengguna::SATKER->value)->where('satker_id', $baris->satker_id)
            ->where('aktif', true)->firstOrFail();
        $rek = $baris->tindakan->rekomendasi;
        $sebelum = (int) LogAktivitas::max('id');

        $this->actingAs($pj)->post(route('tanggapan.simpan', $baris), [
            'aksi' => 'draf', 'uraian' => 'Sedang dikumpulkan buktinya.',
        ], ['User-Agent' => 'Uji/1.0'])->assertRedirect();

        $rw = RiwayatBerkas::where('rekomendasi_id', $rek->id)->latest('id')->firstOrFail();
        $log = LogAktivitas::latest('id')->firstOrFail();
        $this->assertSame('berkas', $log->aksi);
        $this->assertSame($rw->aksi, $log->ringkasan);
        $this->assertSame($pj->id, $log->user_id);
        $this->assertSame($pj->name, $log->nama);
        $this->assertSame(['rekomendasi', $rek->id], [$log->subjek_tipe, $log->subjek_id]);
        $this->assertSame('127.0.0.1', $log->ip);
        $this->assertSame('Uji/1.0', $log->agen);
        /* Satu tindakan, satu baris — jaring terakhir tidak mencatat ulang. */
        $this->assertSame(1, LogAktivitas::where('id', '>', $sebelum)->count());
    }

    public function test_dti_hanya_melihat_log_dan_rekomendasi(): void
    {
        $this->masuk('dti@contoh.test');
        $this->get('/')->assertRedirect(route('log'));
        $isi = $this->get(route('log'))->assertOk()->getContent();
        $this->assertStringContainsString('<h2>Aktivitas', $isi);
        $this->assertStringContainsString('Log aktivitas', $isi);
        /* Menu DTI tanpa Data master. */
        $this->assertStringNotContainsString('href="'.route('master').'"', $isi);

        $this->get(route('rekomendasi.index'))->assertOk();
        $this->get(route('laporan.index'))->assertOk();
        $this->get(route('master'))->assertForbidden();

        /* Kiriman apa pun ditolak di pintu — dan penolakannya tercatat. */
        $baris = Sasaran::firstOrFail();
        $this->post(route('sasaran.teruskan', $baris), ['nomor' => 'X', 'tanggal' => now()->toDateString(), 'perihal' => 'Y'])
            ->assertForbidden();
        $this->post(route('master.tambah'), ['nama' => 'Coba'])->assertForbidden();
        $dti = User::where('email', 'dti@contoh.test')->firstOrFail();
        /* Tiga penolakan: membuka Data master, dan dua kiriman. */
        $this->assertSame(3, LogAktivitas::where('aksi', 'akses.ditolak')->where('user_id', $dti->id)->count());

        /* Urusan akunnya sendiri tetap boleh. */
        $this->post(route('pemberitahuan.semua'))->assertRedirect();
    }

    public function test_log_hanya_untuk_dti_dan_admin(): void
    {
        $this->masuk('setba@contoh.test')->get(route('log'))->assertForbidden();
        $this->masuk('medan')->get(route('log'))->assertForbidden();
        $this->masuk('admin@contoh.test')->get(route('log'))->assertOk();
        $this->masuk('pimpinan@contoh.test')->get(route('log'))->assertForbidden();
    }

    public function test_filter_unduhan_dan_keaktifan(): void
    {
        $this->masuk('dti@contoh.test');
        $n = LogAktivitas::where('kelompok', 'berkas')->count();
        $this->get(route('log', ['kelompok' => 'berkas']))->assertOk()->assertSee('<b>'.$n.'</b> aktivitas', false);
        $this->get(route('log', ['q' => 'tidak-ada-yang-begini']))->assertOk()->assertSee('Tidak ada aktivitas pada pilihan ini.');

        $r = $this->get(route('log.unduh', ['kelompok' => 'berkas']))->assertOk();
        $this->assertStringContainsString('text/csv', (string) $r->headers->get('Content-Type'));
        $csv = $r->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFWaktu;Nama;Peran;", $csv);
        $this->assertSame($n + 1, substr_count($csv, "\n"));
        $this->assertTrue(LogAktivitas::where('aksi', 'log.unduh')->exists());

        $this->get(route('log', ['tab' => 'keaktifan']))->assertOk()
            ->assertSee('Keaktifan akun')->assertSee('Terakhir beraktivitas');
    }

    /**
     * Keaktifan akun sesudah akun benar-benar masuk (27 Sep). Dulu error 500
     * begitu ada `terakhir_masuk_pada` — kolomnya terbaca teks, bukan tanggal —
     * dan uji di atas tidak menangkapnya karena tak ada yang masuk lewat
     * formulir.
     */
    public function test_keaktifan_dan_pengguna_sesudah_akun_masuk_sungguhan(): void
    {
        foreach (['setba@contoh.test', 'dti@contoh.test'] as $email) {
            $this->post('/masuk', ['email' => $email, 'password' => 'rahasia123'])->assertRedirect(route('beranda'));
            $this->post(route('keluar'));
        }
        $setba = User::where('email', 'setba@contoh.test')->firstOrFail()->fresh();
        $this->assertInstanceOf(\Carbon\CarbonInterface::class, $setba->terakhir_masuk_pada);

        $isi = $this->masuk('dti@contoh.test')->get(route('log', ['tab' => 'keaktifan']))->assertOk()->getContent();
        $this->assertStringContainsString(\App\Support\Tampil::waktuLog($setba->terakhir_masuk_pada), $isi);
        $this->assertStringContainsString('Dipakai', $isi);

        $this->masuk('setba@contoh.test')->get(route('master', ['tab' => 'pengguna']))->assertOk()
            ->assertSee(\App\Support\Tampil::waktuLog($setba->terakhir_masuk_pada));
    }

    public function test_log_yang_melewati_masa_simpan_dipangkas(): void
    {
        $lama = LogAktivitas::create(['waktu' => now()->subDays(2000), 'aksi' => 'berkas', 'kelompok' => 'berkas', 'ringkasan' => 'lama sekali']);
        $baru = LogAktivitas::create(['waktu' => now()->subDays(10), 'aksi' => 'berkas', 'kelompok' => 'berkas', 'ringkasan' => 'baru saja']);
        $n = LogAktivitas::count();

        Artisan::call('model:prune', ['--model' => [LogAktivitas::class]]);

        $this->assertNull(LogAktivitas::find($lama->id));
        $this->assertNotNull(LogAktivitas::find($baru->id));
        $this->assertLessThan($n, LogAktivitas::count());
    }
}
