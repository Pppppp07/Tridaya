<?php

namespace Tests\Feature;

use App\Models\Rekomendasi;
use App\Models\Satker;
use App\Support\Dasbor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Ringkasan — dasbor pemantauan yang menggantikan Ringkasan lama (25 Sep 2026),
 * padanan `DasborUji` prototipe. Seluruh angkanya per TINDAK LANJUT SATUAN
 * KERJA: 102 tindak lanjut di 49 rekomendasi.
 *
 * Angka di sini sengaja disebut apa adanya dan sama dengan prototipe —
 * dicocokkan berdampingan, teks demi teks. Kalau rumusnya bergeser, ujinya
 * yang memberi tahu, bukan orang yang membaca dasbornya.
 */
class RingkasanTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        $this->masuk('setba@contoh.test');
    }

    private function idSatker(string $pendek): int
    {
        return Satker::all()->first(fn ($s) => str_ends_with($s->nama, $pendek))->id;
    }

    public function test_lima_kartu_menghitung_tindak_lanjut_satuan_kerja(): void
    {
        $this->get('/ringkasan')->assertOk()
            ->assertSeeInOrder(['Seluruh', '102', 'tindak lanjut satuan kerja'])
            ->assertSeeInOrder(['Memadai', '44%', '45 dari 102 tindak lanjut'])
            ->assertSeeInOrder(['Belum memadai', '57', 'naik 20 sejak Juli'])
            ->assertSeeInOrder(['Menunggu satuan kerja', '29', 'dari 57 belum memadai'])
            ->assertSeeInOrder(['Sisa nilai', 'Rp 567 jt', 'Rp 611 jt', 'diakui dari', 'Rp 1.2 M'])
            ->assertSeeInOrder(['SS di SIPTL', '37%', '31 dari 83', 'tindak lanjut LHP'])
            ->assertSee('Klik kartu untuk melihat rinciannya.')
            /* Kartu Rekomendasi dibuang 24 Sep. */
            ->assertDontSee('39 LHP');
    }

    public function test_kartu_membuka_rinciannya_dengan_bagian_bagian_angkanya(): void
    {
        $this->get('/ringkasan?kartu=tunggu')->assertOk()
            ->assertSeeInOrder(['29', 'di satuan kerja', '15', 'di Setba', '9', 'di UKI', '4', 'di Inspektorat'])
            ->assertSee('Menunggu per satuan kerja')
            ->assertSee('Posisi berkas');

        $this->get('/ringkasan?kartu=M')->assertOk()
            ->assertSee('Dari 102 tindak lanjut satuan kerja di 49 rekomendasi.')
            ->assertSee('Per bulan');

        $this->get('/ringkasan?kartu=BM')->assertOk()
            ->assertSeeInOrder(['45', 'LHP', '12', 'LHA'])
            ->assertSee('Sisa nilai terbesar');

        /* Matriks BPSDM | SIPTL: 7 tindak lanjut memadai tapi belum SS. */
        $this->get('/ringkasan?kartu=ss')->assertOk()
            ->assertSeeInOrder(['BPSDM', '102 tindak lanjut', 'SIPTL', '83 tindak lanjut LHP'])
            ->assertSeeInOrder(['7', 'tindak lanjut sudah memadai di BPSDM, tapi belum SS di SIPTL.'])
            ->assertSeeInOrder(['Sisa:', 'Rp 567 jt', 'di BPSDM', 'Rp 669 jt', 'di SIPTL']);
    }

    public function test_filter_memotong_per_tindak_lanjut(): void
    {
        /* Satu satuan kerja: hanya barisnya sendiri yang dihitung. */
        $medan = $this->idSatker('Medan');
        $this->get('/ringkasan?satker[]='.$medan)->assertOk()
            ->assertSeeInOrder(['7', 'dari 102 tindak lanjut'])
            ->assertSeeInOrder(['43%', '3 dari 7 tindak lanjut']);

        /* Status SIPTL BT: yang tersisa baris ber-BT, bukan seluruh
           rekomendasi yang salah satu barisnya BT. */
        $this->get('/ringkasan?bpk[]=BT')->assertOk()
            ->assertSeeInOrder(['50', 'dari 102 tindak lanjut'])
            ->assertSeeInOrder(['10%', '5 dari 50 tindak lanjut'])
            ->assertSeeInOrder(['0%', '0 dari 50', 'tindak lanjut LHP']);

        /* Belum memadai: kartu mengabaikan filternya sendiri, bar hasil tidak. */
        $this->get('/ringkasan?hasil=BM')->assertOk()
            ->assertSeeInOrder(['57', 'dari 102 tindak lanjut'])
            ->assertSeeInOrder(['44%', '45 dari 102 tindak lanjut']);
    }

    public function test_tombol_dijawab_dengan_alamat_baru(): void
    {
        $this->get('/ringkasan?ubah=kartu:M')->assertRedirect(route('ringkasan', ['kartu' => 'M']));
        $medan = $this->idSatker('Medan');
        $this->get('/ringkasan?kartu=M&ubah=satker:'.$medan)
            ->assertRedirect(route('ringkasan', ['satker' => [$medan], 'kartu' => 'M']));
        /* Klik lagi melepasnya; "Hapus filter" menyisakan kartu yang terbuka. */
        $this->get('/ringkasan?satker[]='.$medan.'&ubah=satker:'.$medan)->assertRedirect(route('ringkasan'));
        $this->get('/ringkasan?satker[]='.$medan.'&tahun[]=2025&kartu=sisa&ubah=kosongkan')
            ->assertRedirect(route('ringkasan', ['kartu' => 'sisa']));
        /* Jenis tanpa SIPTL membuang filter status BPK. */
        $this->get('/ringkasan?bpk[]=BT&ubah=jenis:LHA')->assertRedirect(route('ringkasan', ['jenis' => 'LHA']));
        /* Filter tambahan: dipasang, lalu dibuang. */
        $this->get('/ringkasan?ubah=pasang:sifat')->assertRedirect(route('ringkasan', ['pasang' => ['sifat']]));
        $this->get('/ringkasan?ubah=lepas:sifat&pasang[]=sifat')->assertRedirect(route('ringkasan'));
    }

    public function test_seluruh_tombol_bekerja_tanpa_skrip(): void
    {
        $isi = $this->get('/ringkasan?kartu=BM')->assertOk()->getContent();
        $this->assertStringContainsString('<form class="body dsb" method="get"', $isi);
        $this->assertStringContainsString('name="ubah" value="kartu:BM@dsb-rincian"', $isi);
        $this->assertStringContainsString('name="ubah" value="hal:tabel"', $isi);
        /* Baris "Sisa nilai terbesar" membuka rinciannya lewat server. */
        $this->assertStringContainsString('name="lihat" value="', $isi);
    }

    public function test_tabel_keseluruhan_menjumlah_sama_dengan_kartu(): void
    {
        $this->get('/ringkasan?hal=tabel')->assertOk()
            ->assertSee('Tabel keseluruhan')
            ->assertSeeInOrder(['Jumlah seluruhnya', '49', '102', '45', '57', '44%', '83', '31', '2', '50',
                'Rp 1.2 M', 'Rp 669 jt', 'Rp 567 jt']);

        /* Awalnya tertutup semua; tahun yang dibuka menampilkan satuan kerjanya. */
        $this->get('/ringkasan?hal=tabel')->assertDontSee('dsb-tk-lipat" aria-expanded="true"', false);
        $this->get('/ringkasan?hal=tabel&buka[]=2026')->assertOk()
            ->assertSee('dsb-tk-lipat" aria-expanded="true"', false);
    }

    public function test_angka_tabel_adalah_pintasan(): void
    {
        /* Lebih dari satu rekomendasi: daftarnya dibuka di dekat selnya. */
        $this->get('/ringkasan?hal=tabel&ubah=pintas:||BM')
            ->assertRedirect(route('ringkasan', ['hal' => 'tabel', 'pintas' => '||BM']));
        $this->get(route('ringkasan', ['hal' => 'tabel', 'pintas' => '||BM']))->assertOk()
            ->assertSeeInOrder(['Belum memadai', '57 tindak lanjut', 'semua tahun']);

        /* Cuma satu: langsung ke rinciannya. */
        $rek = Rekomendasi::where('kode', '2024.13.I.7.a')->firstOrFail();
        $satker = $rek->sasaran()->first()->satker_id;
        $jawab = $this->get('/ringkasan?hal=tabel&ubah=pintas:2024|'.$satker.'|rek');
        $jawab->assertRedirect();
        $this->assertStringContainsString('/rekomendasi/'.$rek->id, $jawab->headers->get('Location'));
        $this->assertStringContainsString('dari=ringkasan', $jawab->headers->get('Location'));

        /* Kembali dari rinciannya mendarat di tabel yang sama. */
        $this->get(route('rekomendasi.show', ['rekomendasi' => $rek, 'dari' => 'ringkasan', 'ring' => 'hal=tabel&buka%5B0%5D=2024']))
            ->assertOk()
            ->assertSee(e(route('ringkasan', ['hal' => 'tabel', 'buka' => ['2024']])), false);
    }

    public function test_menu_membawa_lagi_filter_terakhir(): void
    {
        $medan = $this->idSatker('Medan');
        $this->get('/ringkasan?satker[]='.$medan.'&kartu=M&hal=tabel&buka[]=2025')->assertOk();
        /* Dibuka lagi lewat menu: filter dan kartunya tetap, halamannya
           kembali ke kartu dan tabelnya tertutup — sama dengan prototipe. */
        $this->get('/ringkasan')->assertRedirect(route('ringkasan', ['satker' => [$medan], 'kartu' => 'M']));
    }

    /**
     * "Hapus filter" tanpa kartu yang terbuka berakhir di alamat kosong. Dulu
     * alamat kosong itu dibaca sebagai "dibuka lewat menu" lalu filter lama
     * dipasang lagi dari sesi — tombolnya seolah tidak berbuat apa-apa
     * (ditemukan 29 Sep saat memeriksa ganti nama). Keadaan baru kini ikut
     * disimpan sebelum dialihkan.
     */
    public function test_hapus_filter_tidak_dikembalikan_oleh_ingatan_menu(): void
    {
        $this->get('/ringkasan?tahun[]=2025')->assertOk();
        $this->get('/ringkasan?tahun[]=2025&ubah=kosongkan')->assertRedirect(route('ringkasan'));
        $this->get('/ringkasan')->assertOk()->assertDontSee('Hapus filter Tahun 2025');

        /* Keping terakhir yang dilepas juga. */
        $this->get('/ringkasan?tahun[]=2025')->assertOk();
        $this->get('/ringkasan?tahun[]=2025&ubah=hapus:tahun')->assertRedirect(route('ringkasan'));
        $this->get('/ringkasan')->assertOk()->assertDontSee('Hapus filter Tahun 2025');
    }

    public function test_lingkup_lha_memakai_sebutannya_sendiri(): void
    {
        $this->get('/ringkasan?jenis=LHA')->assertOk()
            ->assertSee('Belum sesuai')
            ->assertSee('LHA tidak masuk SIPTL');
    }

    public function test_hitungan_sama_dengan_aturan_baris(): void
    {
        /* Pemeriksa silang: kartu dihitung ulang langsung dari baris. */
        $rek = Rekomendasi::with(['temuan.laporan', 'sasaran.satker', 'sasaran.tindakan.bentuk', 'sasaran.riwayatStatus'])->get();
        $t = Dasbor::hitungTindak(Dasbor::butir($rek));
        $this->assertSame([102, 45, 57, 49], [$t['n'], $t['M'], $t['BM'], $t['rek']]);
        $this->assertSame(['satker' => 29, 'setba' => 15, 'uki' => 9, 'itjen' => 4],
            array_intersect_key($t['meja'], array_flip(['satker', 'setba', 'uki', 'itjen'])));
        $this->assertSame(83, $t['bpk']['n']);
        $this->assertSame(31, $t['bpk']['SS']);
    }
}
