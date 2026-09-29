<?php

namespace Tests\Feature;

use App\Enums\PosisiBerkas;
use App\Models\Sasaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Tampilan menurut lebar layar, sejak 22 Sep.
 *
 * Kata Hizkia: "pada mode desktop sidebar menu saat di scrool dia malah ikut
 * naik ke atas", lalu "tingkatkan kembali ... fleksibilitas dan responsive ...
 * informasi yang ditampilkan itu berbeda tergantung kondisi layar". Tata
 * letaknya sendiri diatur CSS salinan prototipe; yang dijaga di sini kaitan
 * markup yang dibutuhkan CSS itu — kelas kolom, nama isian di mode kartu —
 * dan penyebab menu ikut tergulir yang tidak boleh kembali.
 */
class ResponsifTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    public function test_body_tidak_dipatok_setinggi_layar(): void
    {
        /* Body kerangka halaman ini; kalau dipatok setinggi layar, menu samping
           dan batang atas yang lengket di dalamnya ikut tergulir. */
        $css = preg_replace('#/\*.*?\*/#s', '', file_get_contents(public_path('css/simtlhp-tambahan.css')));
        $this->assertDoesNotMatchRegularExpression('/(^|[\s,}])body\s*(,[^{]*)?\{[^}]*(?<!-)height\s*:\s*100%/', $css);
        $this->assertMatchesRegularExpression('/body\s*\{[^}]*min-height\s*:\s*100%/', $css);
    }

    public function test_batang_atas_bisa_diringkas(): void
    {
        $isi = $this->masuk('setba@contoh.test')->get(route('rekomendasi.index'))->assertOk()->getContent();
        $atas = Str::betweenFirst($isi, '<header class="top">', '</header>');

        /* Tulisan tombol utama bisa disembunyikan tanpa hilang dari pembaca
           layar; tanggal dan panah pengguna punya kelasnya sendiri. */
        $this->assertStringContainsString('title="Catat laporan baru"', $atas);
        $this->assertStringContainsString('<span class="teks-tombol">Catat laporan baru</span>', $atas);
        $this->assertStringContainsString('class="lbl tanggal-hari"', $atas);
        $this->assertStringContainsString('class="panah-pengguna"', $atas);
        $this->assertStringNotContainsString('style="display:flex;align-items:center;gap:6px"', $atas);
    }

    public function test_daftar_rekomendasi_berkelas_kolom(): void
    {
        $isi = $this->masuk('setba@contoh.test')->get(route('rekomendasi.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<div class="tw daftar-rek">', $isi);
        foreach (['k-uraian', 'k-satker', 'k-tenggat', 'k-kemajuan'] as $k) {
            $this->assertStringContainsString('class="urutkan '.$k, $isi);
        }
        $this->assertStringContainsString('<div class="uraian-isi">', $isi);
        $this->assertStringContainsString('data-label="Tenggat jawab"', $isi);
        /* Lebar tetapnya pindah ke CSS supaya bisa berubah menurut layar. */
        $this->assertStringNotContainsString('min-width:380px', $isi);
        $this->assertStringNotContainsString('max-width:420px', $isi);
    }

    public function test_daftar_laporan_berkelas_kolom_dan_tanggal_terima_menumpang(): void
    {
        $isi = $this->masuk('setba@contoh.test')->get(route('laporan.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<div class="tw daftar-lap">', $isi);
        foreach (['temuan', 'rekomendasi', 'dana dipulihkan'] as $label) {
            $this->assertStringContainsString('data-label="'.$label.'"', $isi);
        }
        /* Tanggal terima juga tertulis di bawah nomor laporan, untuk layar yang
           menyembunyikan kolom Diterima. */
        $baris = Str::betweenFirst($isi, '<td class="k-nomor">', '</td>');
        $this->assertStringContainsString('<div class="hanya-sempit">', $baris);
        $this->assertStringContainsString('Diterima ', $baris);
        $this->assertStringNotContainsString('min-width:260px', $isi);
    }

    public function test_tabel_tindak_lanjut_berkelas_kolom(): void
    {
        $x = Sasaran::all()->first(fn ($s) => $s->pos() === PosisiBerkas::SETBA_TINJAU);
        $isi = $this->masuk('setba@contoh.test')
            ->get(route('rekomendasi.show', $x->tindakan->rekomendasi))->assertOk()->getContent();

        $this->assertStringContainsString('<col class="c-no">', $isi);
        $this->assertStringContainsString('<col class="c-aksi">', $isi);
        $this->assertStringNotContainsString('<col style="width', $isi);
        foreach (['k-nama', 'k-posisi', 'k-catatan'] as $k) {
            $this->assertStringContainsString('<td class="'.$k.'"', $isi);
        }
        /* Nama status di mode blok sama dengan kepala kolomnya. */
        $this->assertStringContainsString('class="selhasil k-hasil0" data-label="UKI"', $isi);
        $this->assertStringContainsString('class="selhasil k-hasil1" data-label="Itjen"', $isi);

        /* Riwayatnya juga berkelas kolom. */
        foreach (['rw-sumber', 'rw-sel-peristiwa', 'rw-sel-catatan'] as $k) {
            $this->assertStringContainsString('<td class="'.$k.'"', $isi);
        }
    }
}
