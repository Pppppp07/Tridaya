<?php

namespace Tests\Feature;

use App\Enums\PosisiBerkas;
use App\Models\Sasaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Kepala panel kerja di tab Kerjakan baris satuan kerja, sejak 22 Sep — sejak
 * 25 Sep kepala berpita abu `x-panel-kerja`, sama dengan formulir Catat
 * laporan baru.
 *
 * Kata Hizkia, menunjuk "TINDAKAN SETBA  Balai Wil. IV Bandung": "terlihat
 * ambigu penempatannya, nanti dikira tindakan setba itu yang mengisi Balai
 * Wil.IV Bandung". Yang dijaga: nama tindakannya jadi judul, satuan kerjanya
 * disebut sebagai pemilik berkas dalam satu kalimat — di kelima panel Setba —
 * dan label lama yang berisi nama satuan kerja tidak kembali.
 */
class KepalaKerjaTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    private function baris(callable $syarat): Sasaran
    {
        $x = Sasaran::all()->first($syarat);
        $this->assertNotNull($x, 'data contoh harus punya baris ini');

        return $x;
    }

    /** Halaman rincian yang memuat baris itu, dibuka Setba. */
    private function halaman(Sasaran $x): string
    {
        return $this->masuk('setba@contoh.test')
            ->get(route('rekomendasi.show', $x->tindakan->rekomendasi))->assertOk()->getContent();
    }

    private function adaKepala(string $isi, string $judul, Sasaran $x, string $akhir): void
    {
        $this->assertStringContainsString('<section class="kerja fb" aria-label="'.e($judul).'">', $isi);
        $this->assertStringContainsString('<b>'.e($judul).'<span class="info">', $isi);
        $this->assertStringContainsString(
            '<span>Berkas <b>'.e($x->satker->namaPendek()).'</b> '.$akhir.'</span>', $isi);
    }

    public function test_meneruskan_berkas_judulnya_tindakan_bukan_label_nama_satker(): void
    {
        $keUki = $this->baris(fn ($x) => $x->pos() === PosisiBerkas::SETBA_TINJAU);
        $isi = $this->halaman($keUki);
        $this->adaKepala($isi, 'Teruskan ke UKI', $keUki, 'dikirim bersama surat di bawah ini.');
        $this->assertStringNotContainsString('Tindakan Setba', $isi);

        $keItjen = $this->baris(fn ($x) => $x->pos() === PosisiBerkas::SETBA_TERUSKAN);
        $this->adaKepala($this->halaman($keItjen), 'Teruskan ke Inspektorat', $keItjen,
            'dikirim bersama surat di bawah ini.');
    }

    public function test_mengirim_ulang_berkas_yang_ditolak(): void
    {
        $x = $this->baris(fn ($x) => $x->pos() === PosisiBerkas::SETBA_KEMBALI);
        $isi = $this->halaman($x);
        $this->adaKepala($isi, 'Kirim ulang ke satuan kerja', $x, 'dikembalikan untuk pemberkasan ulang.');
        $this->assertStringNotContainsString('</svg> Pemberkasan ulang</span>', $isi);
    }

    public function test_tiga_pekerjaan_siptl(): void
    {
        $jenis = fn ($x) => $x->tindakan->rekomendasi->jenis();

        $naik = $this->baris(fn ($x) => $x->perluUnggah($jenis($x)));
        $isi = $this->halaman($naik);
        $this->adaKepala($isi, 'Catat unggahan ke SIPTL', $naik, 'siap diunggah ke SIPTL.');
        $this->assertStringNotContainsString('</svg> Unggah ke SIPTL</span>', $isi);

        $cek = $this->baris(fn ($x) => $x->perluCek($jenis($x)));
        $isi = $this->halaman($cek);
        $this->adaKepala($isi, 'Catat hasil pemantauan BPK', $cek, 'menunggu putusan BPK di SIPTL.');
        $this->assertStringNotContainsString('</svg> Hasil pemantauan BPK</span>', $isi);

        $ulang = $this->baris(fn ($x) => $x->perluKirimUlang($jenis($x)));
        $this->adaKepala($this->halaman($ulang), 'Kirim ulang ke satuan kerja', $ulang,
            'dikembalikan untuk pemberkasan ulang.');
    }
}
