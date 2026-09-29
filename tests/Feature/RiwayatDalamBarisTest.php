<?php

namespace Tests\Feature;

use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Support\Pemberitahuan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Riwayat status tindak lanjut sebagai tab di baris tabel, sejak 21 Sep.
 *
 * Kata Hizkia: "tampilan ini ditampilkan kedalam table tindak lanjut satuan
 * kerja sesuai dengan satuan kerjanya … jadi tombol lihat riwayat dipindahkan
 * ke samping tombol bukti, kerjakan". Dulu kartu tersendiri di bawah tabel,
 * dengan tombol pemilih pasangan dan skrip penukar.
 */
class RiwayatDalamBarisTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    private function halaman(string $akun, Rekomendasi $r): string
    {
        return $this->masuk($akun)->get(route('rekomendasi.show', $r))->assertOk()->getContent();
    }

    private function rek(): Rekomendasi
    {
        /* Empat satuan kerja, keempatnya pernah ditolak. */
        return Rekomendasi::where('kode', '2026.22.I.1.a')->firstOrFail();
    }

    private function baris(Rekomendasi $r, string $nama): Sasaran
    {
        return $r->semuaBaris()->first(fn ($x) => $x->satker->namaPendek() === $nama);
    }

    /** Isi tab Riwayat satu baris, sampai akhir tabelnya. */
    private function tab(string $isi, Sasaran $x): string
    {
        $kunci = $x->tindakan_id.'|'.$x->satker_id;
        $this->assertStringContainsString('data-riwayat-baris="'.$kunci.'"', $isi);

        return Str::before(Str::after($isi, 'data-riwayat-baris="'.$kunci.'"'), '</table>');
    }

    public function test_tiap_baris_punya_tab_riwayat_dan_kartu_lama_hilang(): void
    {
        $isi = $this->halaman('setba@contoh.test', $this->rek());

        /* Satu tab dan satu panel per baris, di samping Bukti dan Kerjakan. */
        $this->assertSame(4, substr_count($isi, 'data-tab-tl="riwayat"'));
        $this->assertSame(4, substr_count($isi, 'data-panel-tl="riwayat"'));
        $bilah = Str::between($isi, 'data-tab-tl="bukti"', 'data-tab-tl="riwayat"');
        $this->assertStringContainsString('Bukti &amp; tanggapan', $bilah);

        /* Kartu tersendiri, tombol pemilih, dan link "Lihat riwayat" tidak ada lagi. */
        $this->assertStringNotContainsString('id="r-riwayat"', $isi);
        $this->assertStringNotContainsString('id="r-perkembangan"', $isi);
        $this->assertStringNotContainsString('data-riwayat-lingkup', $isi);
        $this->assertStringNotContainsString('data-pilih-riwayat', $isi);
        $this->assertStringNotContainsString('data-lihat-riwayat', $isi);
        $this->assertStringNotContainsString('Lihat riwayat', $isi);
    }

    public function test_tab_riwayat_hanya_berisi_baris_itu(): void
    {
        $r = $this->rek();
        $isi = $this->halaman('setba@contoh.test', $r);

        $jakarta = $this->tab($isi, $this->baris($r, 'Balai Wil. III Jakarta'));
        $this->assertStringContainsString('Nomor kuitansi tidak berurutan', $jakarta);
        $this->assertStringNotContainsString('Kuitansi tidak bermeterai', $jakarta);
        $this->assertStringContainsString('Periode 1', $jakarta);
        $this->assertStringContainsString('Perjalanan periode 1', $jakarta);

        /* Barisnya sendiri sudah menyebut satuan kerja dan posisinya. */
        $this->assertStringNotContainsString('Posisi saat ini', $jakarta);
        $this->assertStringNotContainsString('lingkupriwayat', $jakarta);

        $medan = $this->tab($isi, $this->baris($r, 'Balai Wil. I Medan'));
        $this->assertStringContainsString('Kuitansi tidak bermeterai', $medan);
        $this->assertStringNotContainsString('Nomor kuitansi tidak berurutan', $medan);
    }

    public function test_baris_terbaru_tab_sama_dengan_kolom_catatan_terakhir(): void
    {
        $r = $this->rek();
        $isi = $this->halaman('setba@contoh.test', $r);

        foreach ($r->semuaBaris() as $x) {
            $tab = $this->tab($isi, $x);
            $terkini = Str::before(Str::after($tab, 'class="terkini"'), '</tr>');
            $catatan = trim(html_entity_decode(strip_tags(Str::between($terkini, 'class="rw-catatan-teks">', '</p>'))));
            $this->assertNotSame('', $catatan);
            $this->assertStringContainsString('title="'.e($catatan).'"', $isi,
                $x->satker->namaPendek().': kolom Catatan terakhir harus sama dengan baris Terbaru tabnya');
        }
    }

    public function test_satuan_kerja_hanya_membaca_tab_riwayatnya_sendiri(): void
    {
        $isi = $this->halaman('medan', $this->rek());

        $this->assertSame(1, substr_count($isi, 'data-riwayat-baris="'));
        $this->assertStringNotContainsString('Nomor kuitansi tidak berurutan', $isi);
    }

    public function test_pemberitahuan_penerusan_berkas_menuju_riwayat(): void
    {
        /* Pemberitahuan "diteruskan ke UKI/Inspektorat" dulu menunjuk kartu riwayat
           lewat r-perkembangan; kini tab Riwayat di baris satuan kerjanya. */
        $this->assertSame('Riwayat status tindak lanjut', Pemberitahuan::BAGIAN['r-perkembangan']);
        $this->assertSame('Riwayat status tindak lanjut', Pemberitahuan::BAGIAN['r-riwayat']);
    }
}
