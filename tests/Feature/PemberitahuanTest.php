<?php

namespace Tests\Feature;

use App\Models\Notifikasi;
use App\Support\Pemberitahuan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Pemberitahuan: siapa menerima apa, dan kapan sebuah pemberitahuan baru, belum
 * dibaca, atau sudah dibaca.
 *
 * Dirombak 26 Sep (Hizkia: "logika kondisi Notifikasi saat di klik atau belum
 * dilihat … yang sudah dilihat, riwayat notifikasi"). Membuka pemberitahuan kini
 * MENANDAINYA dibaca — dulu hanya "Tandai semua terbaca", supaya pemberitahuan yang
 * dibuka sekilas tidak hilang. Sekarang pemberitahuan yang dibaca tidak hilang (tab
 * "Semua"), dan bisa ditandai belum dibaca lagi dari barisnya. Angka lonceng
 * menghitung pemberitahuan BARU: belum dibaca dan belum pernah tampil di daftarnya.
 */
class PemberitahuanTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    public function test_setba_melihat_pemberitahuan_yang_ditujukan_kepadanya(): void
    {
        $setba = $this->akun('setba@contoh.test');
        $daftar = Pemberitahuan::untuk($setba);

        $this->assertGreaterThan(0, $daftar->count());
        foreach ($daftar as $k) {
            $this->assertContains('setba', $k->untuk_peran);
        }

        $this->actingAs($setba)->get(route('pemberitahuan'))->assertOk()
            ->assertSee('belum dibaca')
            ->assertSee($daftar->count().' pemberitahuan')
            ->assertSee('Belum dibaca')
            ->assertSee('Semua');
    }

    public function test_satuan_kerja_hanya_menerima_pemberitahuan_yang_menyebutnya(): void
    {
        $medan = $this->akun('medan');

        foreach (Pemberitahuan::untuk($medan) as $k) {
            $this->assertContains('satker', $k->untuk_peran);
            $this->assertTrue($k->satker->contains('id', $medan->satker_id),
                "Pemberitahuan {$k->id} bukan untuk satuan kerja ini.");
        }
    }

    public function test_membuka_pemberitahuan_menandainya_dibaca_lalu_mengantar_ke_bagiannya(): void
    {
        $setba = $this->akun('setba@contoh.test');
        $pemberitahuan = Pemberitahuan::untuk($setba)->first(fn ($k) => ! Pemberitahuan::sudahDibaca($k, $setba));
        $this->assertNotNull($pemberitahuan);

        $tujuan = $this->actingAs($setba)->get(route('pemberitahuan.buka', $pemberitahuan))
            ->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith(route('rekomendasi.show', $pemberitahuan->rekomendasi_id), $tujuan);
        parse_str((string) parse_url($tujuan, PHP_URL_QUERY), $kueri);
        $this->assertSame($pemberitahuan->blok, $kueri['sorot'] ?? null);

        $this->assertTrue(Pemberitahuan::sudahDibaca($pemberitahuan->fresh(), $setba));
    }

    /**
     * Bagian rincian tertutup sejak awal (17 Sep), jadi pemberitahuan tentang SATU
     * satuan kerja ikut membawa satuan kerjanya — halamannya membuka tiket dan
     * baris itu lalu menyorotnya. Pemberitahuan SIPTL dulu punya kartunya sendiri;
     * sekarang diantar ke tabel tindak lanjut.
     */
    public function test_pemberitahuan_satu_satuan_kerja_membawa_satuan_kerjanya(): void
    {
        $pemberitahuan = Notifikasi::where('blok', 'r-siptl')->get()
            ->first(fn ($k) => $k->satker()->count() === 1);
        $this->assertNotNull($pemberitahuan, 'data contoh harus punya pemberitahuan SIPTL untuk satu satuan kerja');
        $pimpinan = $this->akun('pimpinan@contoh.test');

        $tujuan = $this->actingAs($pimpinan)->get(route('pemberitahuan.buka', $pemberitahuan))
            ->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($tujuan, PHP_URL_QUERY), $kueri);

        $this->assertSame('r-siptl', $kueri['sorot'] ?? null);
        $this->assertSame((string) $pemberitahuan->satker()->first()->id, $kueri['satker'] ?? null);
    }

    /** Satuan kerja dituju barisnya sendiri, walau pemberitahuannya untuk beberapa satuan kerja. */
    public function test_satuan_kerja_diantar_ke_barisnya_sendiri(): void
    {
        $pemberitahuan = Notifikasi::all()->first(fn ($k) => in_array('satker', $k->untuk_peran, true)
            && $k->blok === 'r-tindaklanjut');
        $this->assertNotNull($pemberitahuan, 'data contoh harus punya pemberitahuan tindak lanjut untuk satuan kerja');
        $satker = \App\Models\User::where('peran', 'satker')->where('aktif', true)
            ->whereIn('satker_id', $pemberitahuan->satker()->pluck('satkers.id'))->firstOrFail();

        $tujuan = $this->actingAs($satker)->get(route('pemberitahuan.buka', $pemberitahuan))
            ->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($tujuan, PHP_URL_QUERY), $kueri);

        $this->assertSame((string) $satker->satker_id, $kueri['satker'] ?? null);
    }

    /**
     * Kata Hizkia (18 Sep): lonceng di sebelah profil "tugasnya sama", jadi
     * butir Pemberitahuan di menu samping dibuang. Sejak 26 Sep lonceng
     * membuka panel (tanpa skrip tetap link ke layarnya); angkanya
     * menghitung pemberitahuan BARU, dan menyala saat layarnya dibuka.
     */
    public function test_pemberitahuan_dibuka_lewat_lonceng_bukan_menu_samping(): void
    {
        $setba = $this->akun('setba@contoh.test');
        $belum = Pemberitahuan::belumDibaca($setba);
        $this->assertGreaterThan(0, $belum);

        foreach (['pemberitahuan' => true, 'rekomendasi.index' => false] as $rute => $menyala) {
            $isi = $this->actingAs($setba)->get(route($rute))->assertOk()->getContent();

            /* Sampai </nav> PERTAMA — menu akun di batang atas (28 Sep) juga
               punya <nav>, dan lonceng terletak sebelum menu itu. */
            $nav = Str::betweenFirst($isi, '<nav class="nav"', '</nav>');
            $this->assertStringNotContainsString('href="'.route('pemberitahuan').'"', $nav);

            $lonceng = Str::between($isi, '<a class="lonceng"', '>');
            $this->assertStringContainsString('href="'.route('pemberitahuan').'"', $lonceng);
            $this->assertStringContainsString('aria-haspopup="dialog"', $lonceng);
            $this->assertStringContainsString($belum.' belum dibaca"', $lonceng);
            $this->assertSame($menyala, str_contains($lonceng, 'aria-current="page"'), $rute);
        }
    }

    /**
     * Angka lonceng = pemberitahuan BARU. Panel yang dibuka memberi lencana "Baru" pada
     * yang baru, lalu mencatat semuanya sudah dilihat: angkanya hilang, titik
     * belum dibacanya tetap.
     */
    public function test_panel_menandai_dilihat_dan_angka_lonceng_hilang(): void
    {
        $setba = $this->akun('setba@contoh.test');
        $baru = Pemberitahuan::jumlahBaru($setba);
        $belum = Pemberitahuan::belumDibaca($setba);
        $this->assertGreaterThan(0, $baru);

        $panel = $this->actingAs($setba)->post(route('pemberitahuan.panel'))->assertOk()->getContent();
        $this->assertSame($baru, substr_count($panel, 'class="kb-lencana"'));
        $this->assertStringContainsString('Tandai semua dibaca', $panel);
        $this->assertStringContainsString('data-tab-pemberitahuan="belum"', $panel);
        $this->assertStringContainsString('Hari ini', $panel);

        $setba->refresh();
        $this->assertSame(0, Pemberitahuan::jumlahBaru($setba));
        $this->assertSame($belum, Pemberitahuan::belumDibaca($setba));

        $isi = $this->actingAs($setba)->get(route('rekomendasi.index'))->getContent();
        $lonceng = Str::betweenFirst($isi, '<a class="lonceng"', '</a>');
        $this->assertStringContainsString('0 baru', $lonceng);
        $this->assertStringNotContainsString('<span class="n">', $lonceng);
    }

    public function test_tandai_satu_pemberitahuan_dibaca_lalu_belum_dibaca_lagi(): void
    {
        $setba = $this->akun('setba@contoh.test');
        $pemberitahuan = Pemberitahuan::untuk($setba)->first(fn ($k) => ! Pemberitahuan::sudahDibaca($k, $setba));
        $belum = Pemberitahuan::belumDibaca($setba);

        $this->actingAs($setba)->post(route('pemberitahuan.tandai', $pemberitahuan), ['baca' => 1], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJson(['belum' => $belum - 1]);
        $this->assertTrue(Pemberitahuan::sudahDibaca($pemberitahuan->fresh(), $setba));

        $this->actingAs($setba)->post(route('pemberitahuan.tandai', $pemberitahuan), ['baca' => 0])->assertRedirect();
        $this->assertFalse(Pemberitahuan::sudahDibaca($pemberitahuan->fresh(), $setba));
        $this->assertSame($belum, Pemberitahuan::belumDibaca($setba));
    }

    public function test_tandai_semua_terbaca_mengosongkan_yang_belum(): void
    {
        $setba = $this->akun('setba@contoh.test');
        $this->assertGreaterThan(0, Pemberitahuan::belumDibaca($setba));

        $this->actingAs($setba)->post(route('pemberitahuan.semua'))->assertRedirect();

        $this->assertSame(0, Pemberitahuan::belumDibaca($setba->fresh()));
    }

    public function test_pemberitahuan_milik_peran_lain_tidak_bisa_dibuka(): void
    {
        $medan = $this->akun('medan');
        $bukan = Notifikasi::all()->first(fn ($k) => ! in_array('satker', $k->untuk_peran, true));
        $this->assertNotNull($bukan);

        $this->actingAs($medan)->get(route('pemberitahuan.buka', $bukan))->assertForbidden();
        $this->actingAs($medan)->post(route('pemberitahuan.tandai', $bukan), ['baca' => 1])->assertForbidden();
    }

    public function test_pemberitahuan_kiriman_sistem_belum_terbaca_siapa_pun(): void
    {
        /* Penyapu draf berjalan sesudah penyemaian: pemberitahuannya tidak punya
           pelaku, jadi Setba pun harus membacanya. */
        $sapuan = Notifikasi::where('aksi', 'like', 'Terkirim otomatis%')->first();
        $this->assertNotNull($sapuan, 'Data contoh perlu memuat kiriman otomatis.');
        $this->assertSame(0, $sapuan->dibaca()->count());
    }

    /** Kotak popup hanya untuk pemberitahuan baru, sekali per sesi. */
    public function test_popup_hanya_untuk_pemberitahuan_baru_sekali(): void
    {
        $setba = $this->akun('setba@contoh.test');

        $this->actingAs($setba)->get(route('rekomendasi.index'))
            ->assertSee('data-popup', false)->assertSee('pemberitahuan baru');
        /* Bingkai dihitung sekali per proses; di layanan tiap permintaan proses baru. */
        \App\Support\Rangka::lupakan();
        $this->actingAs($setba)->get(route('rekomendasi.index'))->assertDontSee('data-popup', false);

        $ringkas = $this->actingAs($setba)->getJson(route('pemberitahuan.ringkas'))->assertOk()->json();
        $this->assertSame(Pemberitahuan::jumlahBaru($setba), $ringkas['baru']);
        $this->assertNull($ringkas['popup']);
    }

    /** Lambang dan warna dari kalimatnya — sama dengan RUPA_PEMBERITAHUAN prototipe. */
    public function test_rupa_pemberitahuan_dibaca_dari_kalimatnya(): void
    {
        $rupa = fn (string $aksi) => Pemberitahuan::rupa(new Notifikasi(['aksi' => $aksi]));

        $this->assertSame(['nada' => 'merah', 'ikon' => 'AlertTriangle'], $rupa('Validasi UKI selesai — belum sesuai — Surat instruksi belum bernomor.'));
        $this->assertSame(['nada' => 'hijau', 'ikon' => 'CheckCircle2'], $rupa('Hasil verifikasi Inspektorat dicatat — memadai'));
        $this->assertSame(['nada' => 'hijau', 'ikon' => 'Landmark'], $rupa('Status SIPTL dicatat — Sudah sesuai'));
        $this->assertSame(['nada' => 'merah', 'ikon' => 'Landmark'], $rupa('Status SIPTL dicatat — Belum sesuai'));
        $this->assertSame(['nada' => 'kuning', 'ikon' => 'Clock'], $rupa('Terkirim otomatis ke Setba — draf belum dikirim lebih dari 7 hari'));
        $this->assertSame(['nada' => 'kuning', 'ikon' => 'RotateCcw'], $rupa('Dikirim ulang ke Balai Wil. II Palembang untuk pemberkasan ulang'));
        $this->assertSame(['nada' => 'biru', 'ikon' => 'FileText'], $rupa('Rekomendasi baru untuk Anda — Penyetoran'));
        $this->assertSame(['nada' => 'biru', 'ikon' => 'TrendingUp'], $rupa('Kemajuan tindak lanjut — 1 dari 2 dokumen'));
        /* Kata di dalam catatan yang dikutip tidak ikut menentukan. */
        $this->assertSame(['nada' => 'biru', 'ikon' => 'Send'],
            $rupa('Berkas dikirim ke Setba — 3 dari 3 dokumen terpenuhi. Catatan: “Sudah memadai.”'));
    }

    /** Halaman Pemberitahuan: satu daftar per hari, yang terbaru di atas. */
    public function test_halaman_dikelompokkan_per_hari(): void
    {
        $setba = $this->akun('setba@contoh.test');
        $isi = $this->actingAs($setba)->get(route('pemberitahuan', ['tab' => 'semua']))->assertOk()->getContent();

        $this->assertStringContainsString('<h4 class="kb-hari-kep">Hari ini</h4>', $isi);
        $this->assertStringContainsString('<h4 class="kb-hari-kep">15 Agu 2026</h4>', $isi);
        $this->assertLessThan(strpos($isi, '15 Agu 2026</h4>'), strpos($isi, 'Hari ini</h4>'));
    }
}
