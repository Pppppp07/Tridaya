<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\LogAktivitas;
use App\Models\Rekomendasi;
use App\Notifications\PemberitahuanEmail;
use App\Support\Pemberitahuan;
use App\Support\Rangka;
use App\Support\Sesi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Profil & pengaturan (28 Sep) — menu akun, lima tab, dan akibatnya ke seluruh
 * aplikasi: pop-up pemberitahuan, email, animasi, halaman pertama. Kata
 * Hizkia: "buat sistem atau page untuk profil User, lengkap dengan pilihan
 * menu menu lainnya yang berkaitan dengan pengaturan profil, dan juga sistem
 * web nya".
 */
class AkunTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    private const ANDROID = 'Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Mobile Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    /**
     * Sesi yang ditulis permintaan uji sebelumnya dibuang — klien uji tidak
     * mengirim balik cookie sesinya, jadi tiap permintaan menulis baris sesi
     * baru untuk akun yang sama. Yang tersisa hanya sesi buatan uji ("sesi-…").
     */
    private function sisakanSesiUji(): void
    {
        DB::table('sessions')->where('id', 'not like', 'sesi-%')->delete();
    }

    public function test_semua_peran_membuka_kelima_tab_akunnya_sendiri(): void
    {
        foreach (['setba@contoh.test', 'uki@contoh.test', 'inspektorat@contoh.test', 'pimpinan@contoh.test',
            'dti@contoh.test', 'admin@contoh.test', 'medan'] as $email) {
            $u = $this->akun($email);
            foreach (['profil', 'keamanan', 'pemberitahuan', 'tampilan', 'aktivitas'] as $tab) {
                Rangka::lupakan();
                $this->actingAs($u)->get(route('akun', ['tab' => $tab]))->assertOk()
                    ->assertSee('Profil & pengaturan')->assertSee($u->name);
            }
        }
    }

    public function test_tombol_pengguna_membuka_menu_akun_dan_keluar(): void
    {
        $isi = $this->masuk('uki@contoh.test')->get(route('rekomendasi.index'))->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('akun').'" data-buka-akun', $isi);
        foreach (['profil', 'keamanan', 'pemberitahuan', 'tampilan', 'aktivitas'] as $t) {
            $this->assertStringContainsString('href="'.route('akun', ['tab' => $t]).'"', $isi);
        }
        $this->assertMatchesRegularExpression('~data-menu-akun.*?Panduan singkat.*?action="'
            .preg_quote(route('keluar'), '~').'".*?Keluar</button>~s', $isi);
    }

    public function test_profil_menyebut_data_pegawai_dan_hak_aksesnya(): void
    {
        $this->masuk('setba@contoh.test')->get(route('akun'))->assertOk()
            ->assertSee('Sri Wahyuni')->assertSee('19780125 200501 2 005')
            ->assertSee('Analis Pemantauan Tindak Lanjut Hasil Pemeriksaan')
            ->assertSee('Sekretariat Badan Pengembangan Sumber Daya Manusia')
            ->assertSee('Mencatat laporan baru')->assertSee('Mengatur Data master dan akun petugas pusat')
            /* Hanya yang bisa dilakukan yang disebut (Hizkia, 28 Sep). */
            ->assertDontSee('Membuka Log aktivitas')->assertDontSee('Tidak bisa');

        Rangka::lupakan();
        $this->masuk('medan')->get(route('akun'))->assertOk()
            ->assertSee('Penanggung jawab unit')->assertSee('Mengunggah bukti, menulis uraian, dan mengirimnya ke Setba')
            ->assertDontSee('Melihat berkas unit kerja lain')->assertDontSee('Mencatat laporan baru');
    }

    public function test_password_baru_diperiksa_berurutan_dan_dibatasi(): void
    {
        $this->masuk('uki@contoh.test');
        $asal = route('akun', ['tab' => 'keamanan']);
        foreach ([
            [['current_password' => '', 'password' => 'passwordBaru2026', 'password_confirmation' => 'passwordBaru2026'], 'current_password', 'Isi password saat ini.'],
            [['current_password' => 'salah-sekali', 'password' => 'pendek1', 'password_confirmation' => 'pendek1'], 'current_password', 'Password saat ini salah.'],
            [['current_password' => 'rahasia123', 'password' => 'pendek1', 'password_confirmation' => 'pendek1'], 'password', 'Password baru minimal 10 karakter.'],
            [['current_password' => 'rahasia123', 'password' => 'tanpaangkasama', 'password_confirmation' => 'tanpaangkasama'], 'password', 'Password baru harus berisi huruf dan angka.'],
            [['current_password' => 'rahasia123', 'password' => 'passwordBaru2026', 'password_confirmation' => 'passwordBaru2027'], 'password', 'Konfirmasi password baru tidak sama.'],
            [['current_password' => 'rahasia123', 'password' => 'rahasia123', 'password_confirmation' => 'rahasia123'], 'password', 'Password baru harus berbeda dari password saat ini.'],
        ] as [$isi, $medan, $pesan]) {
            $this->from($asal)->post(route('akun.password'), $isi)->assertRedirect($asal)
                ->assertSessionHasErrorsIn('password', [$medan => $pesan]);
        }
        /* Kiriman ketujuh dalam semenit ditahan — formulir ini tidak bisa
           dipakai menebak password orang yang meninggalkan komputernya. */
        $this->from($asal)->post(route('akun.password'), ['current_password' => 'coba'])->assertStatus(429);
        $this->assertTrue(Hash::check('rahasia123', $this->akun('uki@contoh.test')->password));
        $this->assertSame(0, LogAktivitas::where('aksi', 'akun.password')->count());
    }

    public function test_ganti_password_mengeluarkan_perangkat_lain_dan_tercatat(): void
    {
        config(['session.driver' => 'database']);
        $u = $this->akun('uki@contoh.test');
        $setba = $this->akun('setba@contoh.test');
        DB::table('sessions')->insert([
            ['id' => 'sesi-uki-lain', 'user_id' => $u->id, 'ip_address' => '10.20.7.52', 'user_agent' => self::ANDROID,
                'payload' => '', 'last_activity' => now()->getTimestamp() - 600],
            ['id' => 'sesi-setba', 'user_id' => $setba->id, 'ip_address' => '10.20.7.53', 'user_agent' => self::ANDROID,
                'payload' => '', 'last_activity' => now()->getTimestamp()],
        ]);

        $this->actingAs($u)->get(route('akun', ['tab' => 'keamanan']))->assertOk()
            ->assertSee('Chrome · Android')->assertSee('Perangkat ini')->assertSee('Terakhir aktif 10 menit lalu · 10.20.7.52')
            ->assertDontSee('sesi-uki-lain');

        $this->sisakanSesiUji();
        $this->post(route('akun.password'), ['current_password' => 'rahasia123', 'password' => 'passwordBaru2026', 'password_confirmation' => 'passwordBaru2026'])
            ->assertRedirect(route('akun', ['tab' => 'keamanan']))
            ->assertSessionHas('pesan_password', 'Password berhasil diganti. Perangkat lain yang sedang aktif telah dikeluarkan.');

        $this->assertTrue(Hash::check('passwordBaru2026', $u->fresh()->password));
        $this->assertFalse(DB::table('sessions')->where('id', 'sesi-uki-lain')->exists(), 'Perangkat lain dikeluarkan.');
        $this->assertTrue(DB::table('sessions')->where('id', 'sesi-setba')->exists(), 'Sesi akun lain tidak tersentuh.');
        $log = LogAktivitas::where('aksi', 'akun.password')->sole();
        $this->assertSame('akun', $log->kelompok);
        $this->assertSame(['perangkat_dikeluarkan' => 1], $log->rincian);
        $this->assertStringNotContainsString('passwordBaru2026', json_encode($log->toArray()));

        /* Masuk dengan password lama gagal, yang baru berhasil. */
        $this->post(route('keluar'));
        $this->post('/masuk', ['email' => 'uki@contoh.test', 'password' => 'rahasia123'])->assertSessionHasErrors('email');
        $this->post('/masuk', ['email' => 'uki@contoh.test', 'password' => 'passwordBaru2026'])->assertRedirect(route('beranda'));
    }

    public function test_perangkat_lain_dikeluarkan_lewat_kuncinya_bukan_id_sesi(): void
    {
        config(['session.driver' => 'database']);
        $u = $this->akun('setba@contoh.test');
        $pimpinan = $this->akun('pimpinan@contoh.test');
        DB::table('sessions')->insert([
            ['id' => 'sesi-setba-1', 'user_id' => $u->id, 'ip_address' => '10.1.1.1', 'user_agent' => self::ANDROID, 'payload' => '', 'last_activity' => now()->getTimestamp()],
            ['id' => 'sesi-setba-2', 'user_id' => $u->id, 'ip_address' => '10.1.1.2',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36 Edg/128.0',
                'payload' => '', 'last_activity' => now()->getTimestamp()],
            ['id' => 'sesi-pimpinan', 'user_id' => $pimpinan->id, 'ip_address' => '10.1.1.3', 'user_agent' => self::ANDROID, 'payload' => '', 'last_activity' => now()->getTimestamp()],
        ]);

        $this->actingAs($u);
        /* Kunci sesi akun lain tidak berlaku di sini. */
        $this->sisakanSesiUji();
        $this->post(route('akun.sesi'), ['sesi' => Sesi::kunci('sesi-pimpinan')])
            ->assertSessionHas('pesan_sesi', 'Perangkat itu sudah tidak masuk.');
        $this->assertTrue(DB::table('sessions')->where('id', 'sesi-pimpinan')->exists());

        $this->sisakanSesiUji();
        $this->post(route('akun.sesi'), ['sesi' => Sesi::kunci('sesi-setba-1')])
            ->assertSessionHas('pesan_sesi', 'Chrome · Android dikeluarkan.');
        $this->assertFalse(DB::table('sessions')->where('id', 'sesi-setba-1')->exists());
        $this->assertTrue(DB::table('sessions')->where('id', 'sesi-setba-2')->exists());

        $this->sisakanSesiUji();
        $this->post(route('akun.sesi'), ['sesi' => 'semua'])->assertSessionHas('pesan_sesi', '1 perangkat dikeluarkan.');
        $this->assertFalse(DB::table('sessions')->where('id', 'sesi-setba-2')->exists());
        $this->assertTrue(DB::table('sessions')->where('id', 'sesi-pimpinan')->exists());
        $this->assertSame(['Mengeluarkan 1 perangkat lain dari akunnya', 'Mengeluarkan Chrome · Android dari akunnya'],
            LogAktivitas::where('aksi', 'akun.sesi')->orderByDesc('id')->pluck('ringkasan')->all());
    }

    public function test_riwayat_masuk_menyebut_percobaan_gagal_dengan_email_sendiri(): void
    {
        $this->post('/masuk', ['email' => 'inspektorat@contoh.test', 'password' => 'salah-satu']);
        $this->post('/masuk', ['email' => 'inspektorat@contoh.test', 'password' => 'rahasia123'])->assertRedirect(route('beranda'));
        $this->post('/masuk', ['email' => 'uki@contoh.test', 'password' => 'salah-lain']);

        Rangka::lupakan();
        $this->get(route('akun', ['tab' => 'keamanan']))->assertOk()
            ->assertSee('Masuk dengan password')->assertSee('Gagal masuk — password salah')
            ->assertSee('Ada 1 percobaan masuk gagal dengan email Anda sejak masuk sebelumnya.');
    }

    public function test_pengaturan_pemberitahuan_mengatur_popup_dan_email(): void
    {
        Notification::fake();
        $r = Rekomendasi::all()->first(fn ($x) => $x->semuaBaris()->pluck('satker_id')->unique()->count() > 1);
        $satker = $r->semuaBaris()->pluck('satker_id')->unique()->first();
        $pj = \App\Models\Satker::find($satker)->penanggungJawab;
        $setba = $this->akun('setba@contoh.test');

        /* Bawaan: penanggung jawab menerima email, Setba tidak. */
        $this->assertTrue($pj->setelan('email'));
        $this->assertFalse($setba->setelan('email'));

        /* Penanggung jawab mematikan email-nya; Setba menyalakannya. */
        $this->actingAs($pj)->post(route('akun.setelan'), ['bagian' => 'pemberitahuan', 'popup' => '1'])
            ->assertRedirect(route('akun', ['tab' => 'pemberitahuan']))->assertSessionHas('pesan_akun', 'Pengaturan tersimpan.');
        $this->actingAs($setba)->post(route('akun.setelan'), ['bagian' => 'pemberitahuan', 'popup' => '1', 'email' => '1']);
        $this->assertFalse($pj->fresh()->setelan('email'));
        $this->assertTrue($setba->fresh()->setelan('email'));

        auth()->logout();
        Pemberitahuan::tulis($r, 'Dikirim ulang untuk pemberkasan ulang', [PeranPengguna::SATKER, PeranPengguna::SETBA], 'r-riwayat', [$satker]);
        $this->app->terminate();
        Notification::assertNotSentTo($pj, PemberitahuanEmail::class);
        Notification::assertSentTo($setba, PemberitahuanEmail::class, function ($n) use ($setba) {
            $email = $n->toMail($setba);

            return str_contains($email->salutation, 'Anda mengaktifkan pemberitahuan lewat email')
                && str_contains($email->salutation, 'Profil & pengaturan → Pemberitahuan');
        });

        /* Tanpa perubahan: tidak ada yang dicatat. */
        $this->actingAs($setba)->post(route('akun.setelan'), ['bagian' => 'pemberitahuan', 'popup' => '1', 'email' => '1'])
            ->assertSessionHas('pesan_akun', 'Tidak ada yang berubah.');
        $log = LogAktivitas::where('aksi', 'akun.setelan')->where('user_id', $setba->id)->sole();
        $this->assertSame(['sebelum' => ['email' => false], 'sesudah' => ['email' => true]], $log->rincian);

        /* Pop-up dimatikan: pemberitahuan baru tetap dihitung di lonceng, tapi tidak dimunculkan sebagai pop-up. */
        $this->post(route('akun.setelan'), ['bagian' => 'pemberitahuan', 'email' => '1']);
        Rangka::lupakan();
        $this->assertNull(Rangka::popupPemberitahuan($setba->fresh(), 3));
    }

    public function test_tampilan_mengatur_animasi_dan_halaman_pertama(): void
    {
        $this->masuk('setba@contoh.test')->get(route('beranda'))->assertRedirect(route('rekomendasi.index'));

        $this->post(route('akun.setelan'), ['bagian' => 'tampilan', 'animasi' => 'kurang', 'beranda' => 'dashboard'])
            ->assertRedirect(route('akun', ['tab' => 'tampilan']))->assertSessionHas('pesan_akun', 'Pengaturan tersimpan.');
        Rangka::lupakan();
        $this->get(route('beranda'))->assertRedirect(route('ringkasan'));
        $this->assertMatchesRegularExpression('~<body\s+data-animasi="kurang"[^>]*>~',
            $this->get(route('akun', ['tab' => 'tampilan']))->assertOk()->getContent());

        /* Halaman yang tidak boleh untuk perannya ditolak. */
        $this->from(route('akun', ['tab' => 'tampilan']))
            ->post(route('akun.setelan'), ['bagian' => 'tampilan', 'animasi' => 'ikut', 'beranda' => 'log'])
            ->assertSessionHasErrorsIn('setelan', ['beranda' => 'Pilih halaman pertama dari daftar.']);

        $log = LogAktivitas::where('aksi', 'akun.setelan')->sole();
        $this->assertSame('Mengubah pengaturan tampilan', $log->ringkasan);
        $this->assertSame(['sebelum' => ['animasi' => 'ikut', 'beranda' => 'rekomendasi'],
            'sesudah' => ['animasi' => 'kurang', 'beranda' => 'dashboard']], $log->rincian);
        /* Sebutannya di log dibaca orang, bukan kunci mentahnya. */
        Rangka::lupakan();
        $this->get(route('akun', ['tab' => 'aktivitas']))->assertOk()
            ->assertSee('Animasi')->assertSee('dikurangi')->assertSee('Halaman pertama')->assertSee('Dashboard');
    }

    public function test_akun_yang_hanya_melihat_tetap_mengurus_akunnya_sendiri(): void
    {
        $this->masuk('dti@contoh.test')
            ->post(route('akun.setelan'), ['bagian' => 'tampilan', 'animasi' => 'ikut', 'beranda' => 'rekomendasi'])
            ->assertRedirect(route('akun', ['tab' => 'tampilan']));
        $this->assertSame('rekomendasi', $this->akun('dti@contoh.test')->setelan('beranda'));
        $this->post(route('akun.password'), ['current_password' => 'rahasia123', 'password' => 'passwordBaru2026', 'password_confirmation' => 'passwordBaru2026'])
            ->assertRedirect(route('akun', ['tab' => 'keamanan']));

        /* Kiriman lain tetap ditolak. */
        $this->post(route('master.unit.tambah'), ['nama' => 'Coba'])->assertForbidden();
    }

    public function test_aktivitas_saya_hanya_milik_akun_ini(): void
    {
        $setba = $this->akun('setba@contoh.test');
        $milik = LogAktivitas::where('user_id', $setba->id)->whereNotIn('aksi', ['masuk', 'keluar'])->count();
        $this->assertGreaterThan(0, $milik);

        $isi = $this->actingAs($setba)->get(route('akun', ['tab' => 'aktivitas']))->assertOk()
            ->assertSee('<b>'.$milik.'</b> aktivitas', false)->getContent();
        $this->assertStringContainsString('class="lg-objek" href="'.url('/rekomendasi/'), $isi);

        Rangka::lupakan();
        $this->get(route('akun', ['tab' => 'aktivitas', 'kelompok' => 'master']))->assertOk()
            ->assertSee('<b>0</b> aktivitas', false)->assertSee('Belum ada aktivitas pada pilihan ini.');
    }
}
