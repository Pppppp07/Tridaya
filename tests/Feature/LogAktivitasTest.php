<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Models\LogAktivitas;
use App\Models\RiwayatBerkas;
use App\Models\Sasaran;
use App\Models\Satker;
use App\Models\User;
use App\Support\Aktivitas;
use App\Support\AlertKeamanan;
use App\Support\Tampil;
use App\Support\TampilanLog;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/** Log aktivitas dan akun DTI. */
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
        /* Satu baris log per baris riwayat yang disalin seeder. */
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
        $bukti = $rek->permintaanUntuk($baris->satker_id, $baris->tindakan_id)
            ->flatMap->item->where('terpenuhi', false)
            ->map(fn ($i) => ['nama' => 'Bukti '.$i->nama, 'link' => 'https://contoh.test/'.$i->id,
                'jenis' => $i->nama, 'untuk' => $i->id])
            ->values()->all();
        $sebelum = (int) LogAktivitas::max('id');

        $this->actingAs($pj)->post(route('tanggapan.simpan', $baris), [
            'aksi' => 'kirim', 'uraian' => 'Bukti sudah lengkap.', 'bukti' => $bukti,
        ], ['User-Agent' => 'Uji/1.0'])->assertRedirect();
        $this->assertSame(PosisiBerkas::SETBA_TINJAU, $baris->fresh()->pos());

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
     * Keaktifan akun sesudah akun benar-benar masuk (`terakhir_masuk_pada` harus terbaca sebagai
     * tanggal).
     */
    public function test_keaktifan_dan_pengguna_sesudah_akun_masuk_sungguhan(): void
    {
        foreach (['setba@contoh.test', 'dti@contoh.test'] as $email) {
            $this->post('/masuk', ['email' => $email, 'password' => 'rahasia123'])->assertRedirect(route('beranda'));
            $this->post(route('keluar'));
        }
        $setba = User::where('email', 'setba@contoh.test')->firstOrFail()->fresh();
        $this->assertInstanceOf(CarbonInterface::class, $setba->terakhir_masuk_pada);

        $isi = $this->masuk('dti@contoh.test')->get(route('log', ['tab' => 'keaktifan']))->assertOk()->getContent();
        $this->assertStringContainsString(Tampil::waktuLog($setba->terakhir_masuk_pada), $isi);
        $this->assertStringContainsString('Dipakai', $isi);

        $this->masuk('admin@contoh.test')->get(route('master', ['tab' => 'pengguna']))->assertOk()
            ->assertSee(Tampil::waktuLog($setba->terakhir_masuk_pada));
    }

    public function test_rincian_menunjukkan_bukti_hasil_dan_asal_tanpa_memutus_niat(): void
    {
        $x = LogAktivitas::create([
            'waktu' => now(), 'aksi' => 'akses.ditolak', 'kelompok' => 'akses',
            'nama' => 'Nama saat kejadian', 'peran' => 'uki',
            'ringkasan' => 'Permintaan ditolak untuk diperiksa', 'ip' => '203.0.113.9',
            'agen' => '<script>perangkat</script>',
            'rincian' => ['code_http' => 403, 'konteks' => ['sumber' => 'web', 'metode' => 'POST', 'alamat' => '/data-master/pengguna', 'rute' => 'master.pengguna.tambah']],
        ]);
        $lain = LogAktivitas::create(['waktu' => now(), 'aksi' => 'berkas', 'kelompok' => 'berkas', 'ringkasan' => 'Kegiatan lain yang tidak diminta']);

        $isi = $this->masuk('dti@contoh.test')->get(route('log', ['kejadian' => $x->id, 'rinci' => $x->id]))->assertOk()
            ->assertSee('<b>1</b> aktivitas', false)->assertSee('Ditolak')->assertSee('Permintaan web')
            ->assertSee('Peran pada saat kejadian')->assertSee('403')->assertSee('/data-master/pengguna')
            ->assertSee('Nama saat kejadian')->assertSee('Semua aktivitas')->assertDontSee($lain->ringkasan)->getContent();
        $this->assertStringContainsString('&lt;script&gt;perangkat&lt;/script&gt;', $isi);
        $this->assertStringNotContainsString('<script>perangkat</script>', $isi);
        $this->assertStringNotContainsString('id="lg-r-'.$x->id.'" hidden', $isi);
        $tertutup = $this->get(route('log', ['kejadian' => $x->id, 'rinci' => $lain->id]))->assertOk()
            ->assertDontSee('id="lg-r-'.$lain->id.'"', false)->getContent();
        $this->assertMatchesRegularExpression('/id="lg-r-'.$x->id.'"[^>]*\bhidden\b/', $tertutup);
    }

    public function test_csv_menambah_hasil_asal_dan_menetralisir_formula_tanpa_mengubah_log(): void
    {
        $x = LogAktivitas::create([
            'waktu' => now(), 'aksi' => 'masuk.gagal', 'kelompok' => 'akses', 'nama' => '=1+1',
            'ringkasan' => '+SUM(1;1)', 'agen' => '@perangkat', 'ip' => '203.0.113.10',
            'rincian' => ['konteks' => ['sumber' => 'web', 'metode' => 'POST', 'alamat' => '/masuk']],
        ]);
        $r = $this->masuk('dti@contoh.test')->get(route('log.unduh', ['kejadian' => $x->id]))->assertOk();
        $csv = $r->streamedContent();
        $this->assertStringContainsString(';Hasil;', $csv);
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringContainsString("'+SUM(1;1)", $csv);
        $this->assertStringContainsString("'@perangkat", $csv);
        $this->assertStringContainsString(';Gagal;"Permintaan web";POST;/masuk;', $csv);
        $this->assertSame('=1+1', $x->fresh()->nama);
        $this->assertSame('+SUM(1;1)', $x->fresh()->ringkasan);
    }

    public function test_log_lama_tidak_ditebak_asalnya_dan_tidak_dinilai_dari_teks_ringkasan(): void
    {
        $lama = new LogAktivitas(['aksi' => 'master.intern.saklar', 'ringkasan' => 'Gagal adalah nama kategori', 'ip' => null]);
        $this->assertSame('Log lama — asal tidak tercatat', TampilanLog::asal($lama));
        $this->assertSame(['nama' => 'Tercatat', 'kelas' => 'tercatat'], TampilanLog::hasil($lama));
        $lama->nama = 'Perintah peladen';
        $this->assertSame('Perintah server', TampilanLog::asal($lama));
        $lama->rincian = ['konteks' => ['sumber' => 'web', 'metode' => 'POST', 'alamat' => '/data-master']];
        $this->assertSame('Permintaan web', TampilanLog::asal($lama));
    }

    public function test_istilah_baru_mencari_log_lama_tanpa_melewati_filter_atau_mengubah_audit(): void
    {
        $buat = static fn ($kelompok, $aksi, $ringkasan) => LogAktivitas::create([
            'waktu' => now(), 'kelompok' => $kelompok, 'aksi' => $aksi,
            'nama' => 'Perintah peladen', 'ringkasan' => $ringkasan,
        ]);
        $server = $buat('master', 'master.unit.ubah', 'Tindakan uji melalui perintah peladen lintasan-uji');
        $email = $buat('pengguna', 'master.pengguna.hubungkan', 'Akun uji dihubungkan menurut surel lintasan-uji');
        $berkas = $buat('berkas', 'berkas', 'Berkas berbeda: perintah peladen lintasan-uji dan menurut surel lintasan-uji');
        $kategori = $buat('master', 'master.intern.ubah', 'Kategori berbeda: perintah peladen lintasan-uji dan menurut surel lintasan-uji');
        $asli = [$server->id => $server->fresh()->getRawOriginal(), $email->id => $email->fresh()->getRawOriginal()];

        $this->masuk('admin@contoh.test');
        $this->get(route('log', ['q' => 'perintah server lintasan-uji', 'kelompok' => 'master']))->assertOk()
            ->assertSee('id="lg-r-'.$server->id.'"', false)->assertSee('id="lg-r-'.$kategori->id.'"', false)
            ->assertDontSee('id="lg-r-'.$berkas->id.'"', false)
            ->assertSee('Tindakan uji melalui perintah server lintasan-uji');
        $this->get(route('log', ['q' => 'menurut email lintasan-uji', 'kelompok' => 'pengguna']))->assertOk()
            ->assertSee('<b>1</b> aktivitas', false)->assertSee('id="lg-r-'.$email->id.'"', false)
            ->assertDontSee('id="lg-r-'.$berkas->id.'"', false)->assertDontSee('id="lg-r-'.$kategori->id.'"', false);

        $this->get(route('master', ['tab' => 'riwayat', 'filter' => 'unit', 'cari' => 'perintah server lintasan-uji']))->assertOk()
            ->assertSee(route('log', ['kejadian' => $server->id, 'rinci' => $server->id]))
            ->assertDontSee(route('log', ['kejadian' => $kategori->id, 'rinci' => $kategori->id]))
            ->assertDontSee(route('log', ['kejadian' => $berkas->id, 'rinci' => $berkas->id]));
        $this->get(route('master', ['tab' => 'riwayat', 'filter' => 'pengguna', 'cari' => 'menurut email lintasan-uji']))->assertOk()
            ->assertSee(route('log', ['kejadian' => $email->id, 'rinci' => $email->id]))
            ->assertDontSee(route('log', ['kejadian' => $kategori->id, 'rinci' => $kategori->id]))
            ->assertDontSee(route('log', ['kejadian' => $berkas->id, 'rinci' => $berkas->id]));

        $csv = $this->get(route('log.unduh', ['q' => 'menurut email lintasan-uji', 'kelompok' => 'pengguna']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Akun uji dihubungkan menurut surel lintasan-uji', $csv);
        $this->assertStringNotContainsString('menurut email lintasan-uji', $csv);
        $this->assertSame($asli[$server->id], $server->fresh()->getRawOriginal());
        $this->assertSame($asli[$email->id], $email->fresh()->getRawOriginal());
    }

    public function test_alias_pencarian_memakai_batas_kata_dan_teks_bebas_tetap_asli(): void
    {
        $this->assertSame(['server email', 'peladen email', 'server surel', 'peladen surel'], TampilanLog::varianCari('server email'));
        $this->assertSame(['Mailserver Serverku Emailnya email_arsip'], TampilanLog::varianCari('Mailserver Serverku Emailnya email_arsip'));
        $this->assertSame(['Server EMAIL', 'Peladen EMAIL', 'Server SUREL', 'Peladen SUREL'], TampilanLog::varianCari('Server EMAIL'));

        $manusia = new LogAktivitas([
            'user_id' => 123, 'peran' => 'uki', 'nama' => 'Peladen Surel',
            'ringkasan' => 'Mengubah kategori Peladen dan Surel',
        ]);
        $this->assertSame('Peladen Surel', TampilanLog::nama($manusia));
        $this->assertSame('Mengubah kategori Peladen dan Surel', TampilanLog::ringkasan($manusia));
        $this->assertSame('Nama kategori Peladen / Surel', TampilanLog::teksUi('Nama kategori Peladen / Surel'));
        $manusia->nama = 'Perintah peladen';
        $this->assertSame('Perintah peladen', TampilanLog::nama($manusia));
    }

    public function test_panel_alert_menampilkan_bukti_tertaut_dan_hanya_admin_mendapat_form_tinjauan(): void
    {
        DB::table('pemantauan_keamanan')->where('id', 1)->update(['mulai_pada' => Aktivitas::waktuKini()]);
        $log = LogAktivitas::create([
            'waktu' => Aktivitas::waktuKini(), 'aksi' => 'masuk.dikunci', 'kelompok' => 'akses',
            'nama' => 'identitas-yang-dicoba@example.test', 'ringkasan' => 'Percobaan dibatasi untuk pemeriksaan',
            'rincian' => ['konteks' => ['sumber' => 'web', 'metode' => 'POST', 'alamat' => '/masuk']],
        ]);
        AlertKeamanan::proses($log);
        $alert = \App\Models\AlertKeamanan::where('aturan', 'masuk_dikunci')->firstOrFail();
        $alert->tinjauan()->create([
            'nama' => 'Admin sebelumnya', 'peran' => 'admin', 'keputusan' => 'perlu_tindak_lanjut',
            'catatan' => '<script>catatan</script>', 'waktu' => Aktivitas::waktuKini(),
        ]);
        $url = route('log', ['tab' => 'alert', 'alert' => $alert->id]);
        $this->masuk('admin@contoh.test')->get($url)->assertOk()->assertSee('Percobaan masuk dibatasi sementara')
            ->assertSee('Buka log sumber')->assertSee('Simpan tinjauan')->assertSee('Identitas yang dicoba / sistem')
            ->assertSee('name="bukti_sampai" value="'.$log->id.'"', false)
            ->assertSee('&lt;script&gt;catatan&lt;/script&gt;', false)->assertDontSee('<script>catatan</script>', false);
        $this->masuk('dti@contoh.test')->get($url)->assertOk()->assertSee('Buka log sumber')
            ->assertSee('Akun DTI membaca bukti')->assertDontSee('Simpan tinjauan')
            ->assertDontSee('action="'.route('log.alert.tinjau', $alert).'"', false);
        $this->get(route('log', ['kejadian' => $log->id]))->assertOk()->assertSee('Alert #'.$alert->id);
        $this->get(route('log', ['tab' => 'alert', 'alert' => 999999]))->assertNotFound();
    }

    public function test_filter_alert_terbuka_memuat_yang_belum_selesai_dan_riwayat_wajar_tetap_bisa_dibuka(): void
    {
        $buat = static fn ($status, $judul) => \App\Models\AlertKeamanan::create([
            'cakupan' => 'uji-tampilan-'.$status,
            'aturan' => 'perubahan_peran', 'tingkat' => 'perhatian', 'status' => $status, 'judul' => $judul, 'alasan' => 'Perlu pemeriksaan.',
            'pertama_pada' => Aktivitas::waktuKini(), 'terakhir_pada' => Aktivitas::waktuKini(),
            'sumber' => 'web', 'bukti' => ['log_ids' => [], 'jumlah' => 1],
        ]);
        $buat('baru', 'Indikasi baru untuk diperiksa');
        $buat('perlu_tindak_lanjut', 'Tindak lanjut masih berjalan');
        $wajar = $buat('wajar', 'Penggunaan dinilai wajar');
        $this->masuk('dti@contoh.test')->get(route('log', ['tab' => 'alert', 'status' => 'terbuka']))->assertOk()
            ->assertSee('<b>2</b> alert sesuai filter', false)->assertSee('Indikasi baru untuk diperiksa')
            ->assertSee('Tindak lanjut masih berjalan')->assertDontSee('Penggunaan dinilai wajar');
        $this->get(route('log', ['tab' => 'alert', 'status' => 'wajar', 'alert' => $wajar->id]))->assertOk()
            ->assertSee('<b>1</b> alert sesuai filter', false)->assertSee('Penggunaan dinilai wajar')->assertSee('Log sumber tidak tersedia lagi');
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
