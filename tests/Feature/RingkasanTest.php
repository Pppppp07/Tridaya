<?php

namespace Tests\Feature;

use App\Models\Laporan;
use App\Models\Rekomendasi;
use App\Models\Satker;
use App\Support\Dasbor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/** Dashboard: seluruh angka per tindak lanjut satuan kerja. */
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

    public function test_empat_kartu_menghitung_tindak_lanjut_satuan_kerja(): void
    {
        /* Bawaannya LHP saja: LHP dan LHA tidak digabung. */
        $this->get('/ringkasan')->assertOk()
            ->assertSeeInOrder(['Seluruh', '83', 'tindak lanjut LHP'])
            ->assertSee('<span class="l">Status', false)
            ->assertSeeInOrder(['46%', '38 memadai', '45 belum memadai'])
            /* Pita kartu Status: hijau yang lolos, merah yang belum. */
            ->assertSee('flex-grow:38;background:'.Dasbor::WARNA['M'], false)
            ->assertSee('flex-grow:45;background:'.Dasbor::WARNA['BM'], false)
            /* Kartu Proses: yang belum lolos menurut tahapnya, satu warna per tahap. */
            ->assertSee('<span class="dsb-pjg">Proses tindak lanjut</span>', false)
            ->assertSeeInOrder(['45', '24 Satker', '11 Setba', '6 UKI', '4 Itjen'])
            ->assertSee('flex-grow:11;background:'.Dasbor::WARNA_MEJA['setba'], false)
            ->assertSeeInOrder(['Sisa nilai', 'Rp 567 jt', 'Rp 611 jt', 'diakui dari', 'Rp 1.2 M'])
            ->assertSeeInOrder(['SS di SIPTL', '86%', '31 dari 36', 'tindak lanjut di SIPTL'])
            ->assertSee('Klik kartu untuk melihat rinciannya.')
            ->assertDontSee('kartu:BM@dsb-rincian', false)
            ->assertDontSee('Menunggu satuan kerja')
            ->assertDontSee('lolos verifikasi')
            ->assertDontSee('39 LHP');
    }

    public function test_kartu_membuka_rinciannya_dengan_bagian_bagian_angkanya(): void
    {
        $this->get('/ringkasan?kartu=tunggu')->assertOk()
            ->assertSee('<h2 id="dsb-rincian-judul">Proses tindak lanjut</h2>', false)
            ->assertSeeInOrder(['24', 'di satuan kerja', '11', 'di Setba', '6', 'di UKI', '4', 'di Inspektorat'])
            /* Kotak, batang per satuan kerja, dan Posisi berkas memakai warna tahap yang sama. */
            ->assertSee('background:'.Dasbor::WARNA_MEJA['uki'], false)
            ->assertSee('Proses per satuan kerja')
            ->assertSeeInOrder(['45', 'tindak lanjut di', '16', 'satuan kerja,', '24', 'menunggu tanggapan satuan kerja.'])
            ->assertSee('Posisi berkas')
            ->assertSee('--w:'.Dasbor::WARNA_MEJA['itjen'], false)
            ->assertDontSee('Menunggu per satuan kerja');

        /* Acuannya tindak lanjut; jumlah rekomendasi tidak disebut. */
        $this->get('/ringkasan?kartu=M')->assertOk()
            ->assertSee('<h2 id="dsb-rincian-judul">Status</h2>', false)
            ->assertSeeInOrder(['38', 'memadai', '45', 'belum memadai'])
            ->assertSee('Dari 83 tindak lanjut satuan kerja, menurut hasil verifikasi Inspektorat (status SIPTL tidak dihitung).')
            ->assertDontSee('di 39 rekomendasi')
            ->assertSee('Per bulan')
            ->assertSee('Sisa nilai terbesar')
            /* Rincian Status hanya sampai Inspektorat. */
            ->assertDontSee('sisa SIPTL');

        /* Kartu "Belum memadai" sudah tidak ada: alamat lamanya tidak membuka rincian. */
        $this->get('/ringkasan?kartu=BM')->assertOk()
            ->assertSee('Klik kartu untuk melihat rinciannya.');

        /* Matriks BPSDM | SIPTL: SIPTL = yang sudah diunggah, ditambah yang belum diunggah. Asal 38
           yang memadai dirinci; sisa SIPTL tetap dari yang diunggah. */
        $this->get('/ringkasan?kartu=ss')->assertOk()
            ->assertSeeInOrder(['BPSDM', '83 tindak lanjut', 'SIPTL', '36 tindak lanjut', '+ 2 belum diunggah'])
            ->assertSeeInOrder(['Dari', '38', 'yang memadai di BPSDM:', '36', 'sudah di SIPTL', '5', 'belum SS',
                '2', 'belum diunggah'])
            ->assertDontSee('dari LHA (tidak masuk SIPTL)')
            ->assertSeeInOrder(['Sisa:', 'Rp 567 jt', 'di BPSDM', 'Rp 102 jt', 'di SIPTL']);
    }

    /** Grafik per bulan satu tahun kalender; alamat lama `?periode=12` jatuh ke bawaan. */
    public function test_per_bulan_memakai_tahun_tanpa_pilihan_12_bulan(): void
    {
        $this->get('/ringkasan?kartu=M')->assertOk()->assertSee('Per bulan')
            ->assertDontSee('12 bulan terakhir')
            ->assertSee('<option value="2026" selected>Tahun 2026</option>', false);
        $this->get('/ringkasan?kartu=M&periode=12')->assertOk()
            ->assertDontSee('12 bulan terakhir')
            ->assertSee('<option value="2026" selected>Tahun 2026</option>', false);
        $this->get('/ringkasan?kartu=M&periode=2025')->assertOk()
            ->assertSee('<option value="2025" selected>Tahun 2025</option>', false);
    }

    public function test_filter_memotong_per_tindak_lanjut(): void
    {
        /* Satu satuan kerja: hanya barisnya sendiri yang dihitung. */
        $medan = $this->idSatker('Medan');
        $this->get('/ringkasan?satker[]='.$medan)->assertOk()
            ->assertSeeInOrder(['6', 'dari 83 tindak lanjut LHP'])
            ->assertSeeInOrder(['33%', '2 memadai', '4 belum memadai']);

        /* Status SIPTL BT: hanya baris yang sudah diunggah dan belum dinilai BPK,
           bukan seluruh rekomendasi yang salah satu barisnya BT. */
        $this->get('/ringkasan?bpk[]=BT')->assertOk()
            ->assertSeeInOrder(['3', 'dari 83 tindak lanjut LHP'])
            ->assertSeeInOrder(['100%', '3 memadai', '0 belum memadai'])
            ->assertSeeInOrder(['0%', '0 dari 3', 'tindak lanjut di SIPTL']);

        /* Belum memadai: kartu mengabaikan filternya sendiri, bar hasil tidak. */
        $this->get('/ringkasan?hasil=BM')->assertOk()
            ->assertSeeInOrder(['45', 'dari 83 tindak lanjut LHP'])
            ->assertSeeInOrder(['46%', '38 memadai', '45 belum memadai']);
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
        /* "Hapus filter" tidak mengganti jenis laporan. */
        $this->get('/ringkasan?jenis=LHA&tahun[]=2025&ubah=kosongkan')->assertRedirect(route('ringkasan', ['jenis' => 'LHA']));
        /* Filter tambahan: dipasang, lalu dibuang. */
        $this->get('/ringkasan?ubah=pasang:sifat')->assertRedirect(route('ringkasan', ['pasang' => ['sifat']]));
        $this->get('/ringkasan?ubah=lepas:sifat&pasang[]=sifat')->assertRedirect(route('ringkasan'));
    }

    public function test_seluruh_tombol_bekerja_tanpa_skrip(): void
    {
        $isi = $this->get('/ringkasan?kartu=M')->assertOk()->getContent();
        $this->assertStringContainsString('<form class="body dsb" method="get"', $isi);
        $this->assertStringContainsString('name="ubah" value="kartu:M@dsb-rincian"', $isi);
        $this->assertStringContainsString('name="ubah" value="hal:tabel"', $isi);
        /* Baris "Sisa nilai terbesar" membuka rinciannya lewat server. */
        $this->assertStringContainsString('name="lihat" value="', $isi);
    }

    public function test_tabel_keseluruhan_menjumlah_sama_dengan_kartu(): void
    {
        $this->get('/ringkasan?hal=tabel')->assertOk()
            ->assertSee('Tabel keseluruhan')
            ->assertSeeInOrder(['Jumlah seluruhnya', '83', '38', '45', '46%', '36', '31', '2', '3',
                'Rp 1.2 M', 'Rp 102 jt', 'Rp 567 jt']);

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
            ->assertSeeInOrder(['Belum memadai', '45 tindak lanjut', 'semua tahun']);

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
        /* Dibuka lagi lewat menu: filter dan kartu tetap, tabel tertutup. */
        $this->get('/ringkasan')->assertRedirect(route('ringkasan', ['satker' => [$medan], 'kartu' => 'M']));
    }

    /** "Hapus filter" tanpa kartu terbuka tidak memasang filter lama dari sesi. */
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
            ->assertSeeInOrder(['Seluruh', '19', 'tindak lanjut LHA'])
            ->assertSee('<span class="l">Status', false)
            ->assertSeeInOrder(['37%', '7 sesuai', '12 belum sesuai'])
            ->assertSeeInOrder(['12', '6 Satker', '3 Setba', '3 UKI'])
            ->assertDontSee('lolos verifikasi');
        $this->get('/ringkasan?jenis=LHA&kartu=M')->assertOk()
            ->assertSee('<h2 id="dsb-rincian-judul">Status</h2>', false)
            ->assertSee('Dari 19 tindak lanjut satuan kerja, menurut hasil verifikasi Inspektorat.')
            ->assertDontSee('status SIPTL tidak dihitung');
    }

    /** LHA tidak diunggah ke SIPTL: kartu, filter, dan kolom SIPTL-nya hilang. */
    public function test_lha_tanpa_tampilan_siptl(): void
    {
        $this->get('/ringkasan?jenis=LHA')->assertOk()
            ->assertDontSee('kartu:ss@dsb-rincian', false)
            ->assertDontSee('SS di SIPTL');
        /* Alamat langsung: kartu SIPTL ditutup dan filter status BPK dibuang. */
        $this->get('/ringkasan?jenis=LHA&kartu=ss&bpk[]=BT')->assertOk()
            ->assertSee('Klik kartu untuk melihat rinciannya.')
            ->assertDontSee('Hapus filter Status BPK');
        $this->get('/ringkasan?jenis=LHA&ubah=kartu:ss')->assertRedirect(route('ringkasan', ['jenis' => 'LHA']));
        $this->get('/ringkasan?jenis=LHA&hal=tabel')->assertOk()
            ->assertDontSee('Status SIPTL')
            ->assertDontSee('Sisa SIPTL')
            ->assertSee('Sisa Inspektorat')
            ->assertSee('masuk kelompok Belum sesuai')
            ->assertDontSee('Lolos verifikasi berarti');

        /* LHP dan tanpa pilihan jenis tetap memuatnya. */
        $this->get('/ringkasan?jenis=LHP')->assertOk()->assertSee('kartu:ss@dsb-rincian', false);
        $this->get('/ringkasan?hal=tabel')->assertOk()->assertSee('Status SIPTL')->assertSee('Sisa SIPTL');
    }

    /** Label BPSDM membawa keterangan bahwa statusnya hasil verifikasi Inspektorat. */
    public function test_label_bpsdm_menjelaskan_keputusan_itjen(): void
    {
        $this->get('/ringkasan?kartu=ss')->assertOk()->assertSee('Status BPSDM mengikuti hasil verifikasi Inspektorat');
        $this->get('/ringkasan?hal=tabel')->assertOk()->assertSee('Status BPSDM mengikuti hasil verifikasi Inspektorat');
    }

    public function test_hitungan_sama_dengan_aturan_baris(): void
    {
        /* Pemeriksa silang: kartu dihitung ulang langsung dari baris. */
        $rek = Rekomendasi::with(['temuan.laporan', 'sasaran.satker', 'sasaran.tindakan.bentuk', 'sasaran.riwayatStatus'])->get();
        $t = Dasbor::hitungTindak(Dasbor::butir($rek));
        $this->assertSame([102, 45, 57, 49], [$t['n'], $t['M'], $t['BM'], $t['rek']]);
        $this->assertSame(['satker' => 30, 'setba' => 14, 'uki' => 9, 'itjen' => 4],
            array_intersect_key($t['meja'], array_flip(['satker', 'setba', 'uki', 'itjen'])));
        /* SIPTL hanya memuat yang sudah diunggah: 36 dari 83 tindak lanjut LHP. */
        $this->assertSame(['n' => 36, 'SS' => 31, 'BS' => 2, 'BT' => 3], $t['bpk']);
    }

    public function test_jenis_laporan_tanpa_tombol_semua(): void
    {
        $isi = $this->get('/ringkasan')->assertOk()->getContent();
        $this->assertStringNotContainsString('name="ubah" value="jenis:"', $isi);
        $this->assertStringContainsString('name="ubah" value="jenis:LHP" aria-pressed="true"', $isi);
        $this->assertStringContainsString('name="ubah" value="jenis:LHA" aria-pressed="false"', $isi);
        $this->assertStringNotContainsString('Hapus filter Jenis laporan', $isi);

        /* Selalu satu jenis: menekan yang sedang berlaku tidak melepasnya. */
        $this->get('/ringkasan?jenis=LHA&ubah=jenis:LHA')->assertRedirect(route('ringkasan', ['jenis' => 'LHA']));
        $this->get('/ringkasan?jenis=LHA&ubah=jenis:LHP')->assertRedirect(route('ringkasan'));
        /* Alamat lama (`jenis=semua`) jatuh ke LHP. */
        $this->get('/ringkasan?jenis=semua')->assertOk()->assertSeeInOrder(['Seluruh', '83', 'tindak lanjut LHP']);
    }

    /** Akun yang hanya punya tindak lanjut LHA langsung melihat LHA, bukan LHP yang kosong. */
    public function test_akun_tanpa_lhp_bawaannya_lha(): void
    {
        Laporan::query()->update(['sumber' => 'LHA']);
        $this->get('/ringkasan')->assertOk()
            ->assertSeeInOrder(['Seluruh', '102', 'tindak lanjut LHA'])
            ->assertSee('name="ubah" value="jenis:LHA" aria-pressed="true"', false);
    }
}
