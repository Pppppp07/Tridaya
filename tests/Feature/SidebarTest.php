<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Laporan;
use App\Models\Rekomendasi;
use App\Models\User;
use App\Support\Rangka;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Menu samping sejak 21 Sep.
 *
 * Kata Hizkia: "tingkatkan fungsional secara sistem dan fleksibilitas pada
 * fitur sidebar ini, optimalisasi dan sesuaikan dalam segala kondisi". Yang
 * dijaga: menu induk menyala di halaman turunan, angka menu disebut artinya,
 * tanda merah di Data master, laci yang bisa diakses, tombol ciut, dan panduan
 * singkat yang isinya menurut peran.
 *
 * 22 Sep: "hilangkan tombol fungsional seperti tombol panah memutar nya lalu
 * ganti gambar tombol ciutkan menu jadi gambar panah kekiri dan hapus tulisan
 * atau judulnya" — tombol ciut tinggal ikon panah di bawah logo, tanpa tombol
 * kembali ke otomatis; kartu bantuan satu link. Sejak identitas Tridaya,
 * informasi akun dan keluar tersedia melalui profil, termasuk pada mode rel.
 * Perilakunya (klik area kosong, tiga mode lebar, laci terkunci) di skrip.
 */
class SidebarTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    /** Tag pembuka link menu yang bertuliskan $nama. */
    private function menu(string $isi, string $nama): string
    {
        $nav = Str::between($isi, '<nav class="nav"', '</nav>');
        $potong = Str::before($nav, '<span class="tulisan">'.$nama.'</span>');
        $awal = strrpos($potong, '<a ');
        $this->assertNotFalse($awal, 'menu '.$nama.' tidak ada');

        return substr($potong, $awal);
    }

    public function test_menu_induk_menyala_di_halaman_turunan(): void
    {
        $this->masuk('setba@contoh.test');

        $daftar = $this->get(route('rekomendasi.index'))->assertOk()->getContent();
        $this->assertStringContainsString('aria-current="page"', $this->menu($daftar, 'Rekomendasi'));

        $rincian = $this->get(route('rekomendasi.show', Rekomendasi::firstOrFail()))->assertOk()->getContent();
        $this->assertStringContainsString('aria-current="true"', $this->menu($rincian, 'Rekomendasi'));
        $this->assertStringNotContainsString('aria-current', $this->menu($rincian, 'Daftar laporan'));

        $laporan = $this->get(route('laporan.show', Laporan::firstOrFail()))->assertOk()->getContent();
        $this->assertStringContainsString('aria-current="true"', $this->menu($laporan, 'Daftar laporan'));
        $this->assertStringNotContainsString('aria-current', $this->menu($laporan, 'Rekomendasi'));
    }

    public function test_angka_menu_disebut_artinya(): void
    {
        $isi = $this->masuk('setba@contoh.test')->get(route('rekomendasi.index'))->assertOk()->getContent();
        $rek = $this->menu($isi, 'Rekomendasi');

        $this->assertStringContainsString('aria-label="Rekomendasi, 19 perlu Anda kerjakan"', $rek);
        $this->assertStringContainsString('title="Rekomendasi — 19 perlu Anda kerjakan"', $rek);

        $this->assertSame('7', Rangka::angka(7));
        $this->assertSame('99', Rangka::angka(99));
        $this->assertSame('99+', Rangka::angka(150));
    }

    public function test_data_master_bertanda_merah_bila_ada_unit_tanpa_penanggung_jawab(): void
    {
        $this->masuk('setba@contoh.test');
        $master = $this->menu($this->get(route('ringkasan'))->getContent(), 'Data master');
        $this->assertStringNotContainsString('genting', $master);

        /* Penanggung jawab satu unit berhenti — akunnya nonaktif. */
        User::where('peran', PeranPengguna::SATKER->value)->where('aktif', true)->firstOrFail()
            ->forceFill(['aktif' => false])->save();
        Rangka::lupakan();

        $master = $this->menu($this->get(route('ringkasan'))->getContent(), 'Data master');
        $this->assertStringContainsString('menu genting', $master);
        $this->assertStringContainsString('aria-label="Data master, 1 unit kerja belum punya penanggung jawab"', $master);

        /* Satuan kerja tidak punya menu Data master sama sekali. */
        $satker = $this->masuk('bandung')->get(route('rekomendasi.index'))->getContent();
        $this->assertStringNotContainsString('<span class="tulisan">Data master</span>', $satker);
    }

    public function test_laci_dan_tombol_ciut_siap_dipakai(): void
    {
        $isi = $this->masuk('setba@contoh.test')->get(route('rekomendasi.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<nav class="nav" id="nav-utama" aria-label="Menu utama" tabindex="-1">', $isi);
        $this->assertMatchesRegularExpression('/data-buka-nav aria-label="Buka menu" aria-expanded="false" aria-controls="nav-utama"/', $isi);
        $this->assertStringContainsString('class="tutup-nav" data-tutup-nav aria-label="Tutup menu"', $isi);
        $this->assertStringContainsString('<div class="tirai-nav" data-tutup-nav aria-hidden="true"></div>', $isi);

        /* Kontrol lebar di bagian atas sidebar, baru muncul lewat skrip. Pilihannya
           dibaca sebelum halaman tergambar supaya menunya tidak melompat —
           tanpa pilihan, rel di bawah 1121 px. */
        $nav = Str::between($isi, '<nav class="nav"', '</nav>');
        $this->assertLessThan(strpos($nav, 'class="menu'), strpos($nav, '<div class="kontrol-nav" data-kontrol-nav hidden>'));
        $this->assertStringContainsString('data-mode-menu="otomatis"', $isi);
        $this->assertStringNotContainsString("localStorage.getItem('simtlhp.navRingkas')", $isi);
        $this->assertStringContainsString("matchMedia('(min-width:1121px)')", $isi);

        /* Tombolnya tinggal ikon panah: panah kiri untuk menciutkan, panah
           kanan untuk melebarkan, tanpa tulisan; tombol kembali ke otomatis
           (panah melingkar) tidak ada lagi. */
        $ciut = Str::betweenFirst($nav, 'class="ciut" data-ciut-nav', '</button>');
        $this->assertStringContainsString('aria-label="Ciutkan menu"', $ciut);
        $this->assertStringContainsString('<path d="m15 18-6-6 6-6"/>', $ciut);
        $this->assertStringContainsString('<path d="m9 18 6-6-6-6"/>', $ciut);
        $this->assertStringNotContainsString('tulisan', $ciut);
        $this->assertStringNotContainsString('otomatis-nav', $isi);
        $this->assertSame(1, substr_count($nav, 'data-ciut-nav'));

        /* Identitas dan keluar kini melalui profil, termasuk pada mode rel. */
        $this->assertStringNotContainsString('data-akun-ringkas', $nav);
        $this->assertStringNotContainsString('Masuk sebagai', $nav);
        $this->assertStringNotContainsString('action="'.route('keluar').'"', $nav);
    }

    /** Sejak 25 Sep menunya bernama "Dashboard" (Hizkia: "ganti namanya jadi Dashboard saja"). */
    public function test_judul_dashboard_sama_dengan_menunya_untuk_pimpinan(): void
    {
        $isi = $this->masuk('pimpinan@contoh.test')->get(route('ringkasan'))->assertOk()->getContent();

        $this->assertStringContainsString('<h1>Dashboard</h1>', $isi);
        $this->assertStringNotContainsString('Beranda', $isi);
        $this->assertStringContainsString('aria-current="page"', $this->menu($isi, 'Dashboard'));
    }

    public function test_panduan_singkat_menurut_peran(): void
    {
        $setba = $this->masuk('setba@contoh.test')->get(route('rekomendasi.index'))->getContent();
        $panduan = Str::between($setba, 'id="panduan"', '<div class="main">');

        $this->assertStringContainsString('Panduan singkat', $panduan);
        $this->assertStringContainsString('Setba — Sekretariat Badan', $panduan);
        $this->assertStringContainsString('Mencatat laporan baru, meneruskan berkas ke UKI dan Inspektorat', $panduan);
        foreach (['Rekomendasi', 'Daftar laporan', 'Dashboard', 'Data master'] as $nama) {
            $this->assertStringContainsString(' '.$nama.'</dt>', $panduan);
        }
        /* Tombol bantuan di kaki sidebar satu link. Menu profil juga boleh
           mempunyai link panduan sendiri. */
        $nav = Str::between($setba, '<nav class="nav"', '</nav>');
        $this->assertSame(1, substr_count($nav, 'href="#panduan"'));
        $this->assertStringContainsString('<a class="nav-panduan" href="#panduan" title="Buka panduan" aria-label="Buka panduan" data-buka-panduan>', $nav);
        $this->assertStringContainsString('<div class="lembar panduan" tabindex="-1" role="dialog" aria-modal="true"', $panduan);
        $this->assertStringContainsString('Klik area kosong di menu, tombol panah di bagian atas sidebar', $panduan);
        $this->assertStringContainsString('Di ponsel, tutup menu dengan tombol ×, tombol Esc', $panduan);

        $satker = $this->masuk('bandung')->get(route('rekomendasi.index'))->getContent();
        $panduan = Str::between($satker, 'id="panduan"', '<div class="main">');
        $this->assertStringContainsString('Satuan kerja · Balai Wil. IV Bandung', $panduan);
        $this->assertStringContainsString('Mengisi tindak lanjut unit Anda', $panduan);
        $this->assertStringContainsString('Rekomendasi yang ditujukan ke unit Anda.', $panduan);
        $this->assertStringNotContainsString(' Data master</dt>', $panduan);
    }
}
