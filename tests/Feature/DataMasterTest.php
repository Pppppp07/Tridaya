<?php

namespace Tests\Feature;

use App\Enums\JenisReferensi;
use App\Models\KategoriTemuan;
use App\Models\Referensi;
use App\Models\Temuan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Data master: dua daftar yang boleh berubah, dan satu aturan yang tidak boleh
 * dilanggar — tidak ada yang dihapus, hanya dinonaktifkan.
 */
class DataMasterTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        $this->masuk('setba@contoh.test');
    }

    public function test_kategori_internal_bisa_ditambah_diganti_nama_dan_dipadamkan(): void
    {
        $this->post(route('master.tambah'), ['nama' => 'Kelebihan pembayaran'])->assertRedirect();
        $baru = Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)
            ->where('nama', 'Kelebihan pembayaran')->first();
        $this->assertNotNull($baru);
        $this->assertTrue($baru->aktif);

        /* Warna label dibuang (27 Sep) — kiriman warna diabaikan. */
        $this->post(route('master.simpan', $baru), ['nama' => 'Kelebihan pembayaran belanja', 'warna' => 'merah'])
            ->assertRedirect();
        $baru->refresh();
        $this->assertSame('Kelebihan pembayaran belanja', $baru->nama);
        $this->assertNotSame('merah', $baru->warna?->value);

        $this->post(route('master.saklar', $baru))->assertRedirect();
        $this->assertFalse($baru->fresh()->aktif);

        /* Ketiganya tercatat di Riwayat perubahan, lengkap sebelum → sesudah. */
        $log = \App\Models\LogAktivitas::where('aksi', 'like', 'master.intern.%')->orderBy('id')->get();
        $this->assertSame(['master.intern.tambah', 'master.intern.ubah', 'master.intern.saklar'], $log->pluck('aksi')->all());
        $this->assertSame(['nama' => 'Kelebihan pembayaran'], $log[1]->rincian['sebelum']);
        $this->assertSame(['aktif' => false], $log[2]->rincian['sesudah']);
        $this->get(route('master', ['tab' => 'riwayat']))->assertOk()
            ->assertSee('Mengubah nama kategori internal')->assertSee('Kelebihan pembayaran belanja');
    }

    /** Nama kategori internal yang sudah dipakai temuan terkunci (27 Sep), sama dengan dua daftar lain. */
    public function test_nama_kategori_internal_yang_dipakai_terkunci(): void
    {
        $dipakai = Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)
            ->whereIn('id', Temuan::whereNotNull('kategori_intern_id')->pluck('kategori_intern_id'))
            ->firstOrFail();
        $nama = $dipakai->nama;
        $this->post(route('master.simpan', $dipakai), ['nama' => 'Nama baru'])->assertSessionHas('gagal');
        $this->assertSame($nama, $dipakai->fresh()->nama);
        /* Jendela Ubah-nya tidak bisa dibuka. */
        $this->get(route('master', ['tab' => 'intern', 'jendela' => 'intern-'.$dipakai->id]))->assertOk()
            ->assertDontSee('Ubah kategori internal');
    }

    public function test_kategori_yang_dipadamkan_tidak_ikut_hilang_dari_temuan_lama(): void
    {
        $dipakai = Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)
            ->whereIn('id', Temuan::whereNotNull('kategori_intern_id')->pluck('kategori_intern_id'))
            ->firstOrFail();
        $jumlah = Temuan::where('kategori_intern_id', $dipakai->id)->count();

        $this->post(route('master.saklar', $dipakai))->assertRedirect();

        $this->assertFalse($dipakai->fresh()->aktif);
        $this->assertSame($jumlah, Temuan::where('kategori_intern_id', $dipakai->id)->count());
    }

    public function test_kategori_temuan_bertambah_menurut_sumber_laporannya(): void
    {
        $this->post(route('master.temuan.tambah'), ['nama' => 'Kelemahan SPI lanjutan', 'sumber' => 'LHP'])
            ->assertRedirect();

        $baru = KategoriTemuan::where('nama', 'Kelemahan SPI lanjutan')->first();
        $this->assertNotNull($baru);
        $this->assertSame('LHP', $baru->sumber->value);

        /* Nama yang sama pada sumber yang sama ditolak. */
        $this->post(route('master.temuan.tambah'), ['nama' => 'kelemahan spi lanjutan', 'sumber' => 'LHP'])
            ->assertSessionHas('gagal');
        $this->assertSame(1, KategoriTemuan::where('sumber', 'LHP')
            ->whereRaw('LOWER(nama) = ?', ['kelemahan spi lanjutan'])->count());

        /* Yang belum dipakai temuan boleh berganti nama … */
        $this->post(route('master.temuan.simpan', $baru), ['nama' => 'Kelemahan SPI'])->assertRedirect();
        $this->assertSame('Kelemahan SPI', $baru->fresh()->nama);

        /* … yang sudah dipakai tidak. */
        $dipakai = KategoriTemuan::whereIn('id', Temuan::whereNotNull('kategori_temuan_id')
            ->pluck('kategori_temuan_id'))->firstOrFail();
        $nama = $dipakai->nama;
        $this->post(route('master.temuan.simpan', $dipakai), ['nama' => 'Nama baru'])
            ->assertSessionHas('gagal');
        $this->assertSame($nama, $dipakai->fresh()->nama);
    }

    public function test_hanya_setba_yang_boleh_mengubah(): void
    {
        $kategori = Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)->firstOrFail();

        $this->masuk('uki@contoh.test');
        $this->get(route('master'))->assertForbidden();
        $this->post(route('master.saklar', $kategori))->assertForbidden();

        $this->masuk('medan');
        $this->post(route('master.tambah'), ['nama' => 'Coba'])->assertForbidden();
    }

    public function test_daftar_yang_menyalin_sop_tidak_bisa_disunting_dari_sini(): void
    {
        $bentuk = Referensi::where('jenis', JenisReferensi::BENTUK_TL->value)->firstOrFail();

        $this->post(route('master.simpan', $bentuk), ['nama' => 'Diubah diam-diam'])->assertForbidden();
        $this->post(route('master.saklar', $bentuk))->assertForbidden();
        $this->post(route('master.sifat.saklar', $bentuk))->assertForbidden();
    }

    /**
     * Enam tab, bukan kartu berlipat yang bertumpuk (27 Sep) — sama dengan
     * prototipe. Bang Kamal: "Gua tuh menghindari banyak scroll."
     */
    public function test_halaman_data_master_bertab_dan_jendelanya_dari_alamat(): void
    {
        $isi = $this->get(route('master'))->assertOk()->getContent();
        foreach (['Unit kerja', 'Pengguna', 'Kategori temuan', 'Kategori internal', 'Sifat rekomendasi', 'Riwayat perubahan'] as $judul) {
            $this->assertMatchesRegularExpression('/<a role="tab"[^>]*>(?:(?!<\/a>).)*'.preg_quote($judul, '/').'/s', $isi);
        }
        $this->assertMatchesRegularExpression('/<a role="tab"[^>]*aria-selected="true"[^>]*>(?:(?!<\/a>).)*Unit kerja/s', $isi);
        $this->assertStringContainsString('<h2>Unit kerja', $isi);

        foreach (['pengguna' => 'Pengguna', 'temuan' => 'Kategori temuan', 'intern' => 'Kategori internal',
            'sifat' => 'Sifat rekomendasi', 'riwayat' => 'Riwayat perubahan'] as $tab => $judul) {
            $this->get(route('master', ['tab' => $tab]))->assertOk()->assertSee('<h2>'.$judul, false);
        }
        /* Alamat lama (?sorot=m-sifat) tetap mendarat di tabnya. */
        $this->get(route('master', ['sorot' => 'm-sifat']))->assertOk()->assertSee('<h2>Sifat rekomendasi', false);

        /* Jendela Tambah digambar server — jalan tanpa skrip. */
        $this->get(route('master', ['tab' => 'unit', 'jendela' => 'unit']))->assertOk()
            ->assertSee('data-tirai-master', false)->assertSee('Tambah unit kerja')->assertSee('wajib diisi');
        /* Warna kategori tidak ada lagi. */
        $this->get(route('master', ['tab' => 'intern']))->assertOk()->assertDontSee('Warna label');
    }

    /**
     * Bang Kamal, menunjuk isian Sifat di formulir: "Iya, ini master." Sifat
     * baru membawa keterangan menuntut penyetoran atau tidak, dan keduanya
     * terkunci begitu dipakai.
     */
    public function test_sifat_rekomendasi_bertambah_dengan_keterangan_uangnya(): void
    {
        $this->post(route('master.sifat.tambah'), ['nama' => 'Kerugian daerah', 'uang' => '1'])->assertRedirect();
        $baru = Referensi::where('jenis', JenisReferensi::SIFAT_REKOM->value)
            ->where('nama', 'Kerugian daerah')->firstOrFail();
        $this->assertTrue($baru->perlu_nilai);
        $this->assertTrue($baru->aktif);

        $this->post(route('master.sifat.tambah'), ['nama' => 'kerugian daerah'])->assertSessionHas('gagal');

        /* Belum dipakai: nama dan keterangannya masih boleh dibetulkan. */
        $this->post(route('master.sifat.simpan', $baru), ['nama' => 'Kerugian keuangan daerah', 'uang' => '0'])
            ->assertRedirect();
        $this->assertSame('Kerugian keuangan daerah', $baru->fresh()->nama);
        $this->assertFalse($baru->fresh()->perlu_nilai);

        /* Sudah dipakai: terkunci. */
        $kerugian = Referensi::where('jenis', JenisReferensi::SIFAT_REKOM->value)
            ->where('nama', 'Informasi kerugian negara')->firstOrFail();
        $this->post(route('master.sifat.simpan', $kerugian), ['nama' => 'Ganti', 'uang' => '0'])
            ->assertSessionHas('gagal');
        $this->assertTrue($kerugian->fresh()->perlu_nilai);
        $this->assertSame('Informasi kerugian negara', $kerugian->fresh()->nama);
    }

    public function test_paling_tidak_satu_sifat_tetap_aktif(): void
    {
        [$satu, $dua] = Referensi::where('jenis', JenisReferensi::SIFAT_REKOM->value)->orderBy('id')->get()->all();

        $this->post(route('master.sifat.saklar', $satu))->assertRedirect();
        $this->assertFalse($satu->fresh()->aktif);

        $this->post(route('master.sifat.saklar', $dua))->assertSessionHas('gagal');
        $this->assertTrue($dua->fresh()->aktif);
    }

    /** Formulir menawarkan sifat yang aktif saja, dan tiap pilihan membawa keterangan uangnya. */
    public function test_formulir_membaca_sifat_dari_data_master(): void
    {
        $this->post(route('master.sifat.tambah'), ['nama' => 'Kerugian daerah', 'uang' => '1']);
        $administratif = Referensi::where('jenis', JenisReferensi::SIFAT_REKOM->value)
            ->where('nama', 'Administratif')->firstOrFail();
        $this->post(route('master.sifat.saklar', $administratif));

        $this->post(route('laporan.baru.simpan'), [
            'aksi' => 'maju',
            'surat' => ['sumber' => 'LHP', 'nomor' => '88/LHP/XVIII/09/2026',
                'tgl_surat' => '2026-08-01', 'tgl_terima' => '2026-08-05'],
            'berkas' => ['judul' => 'Laporan 88', 'link' => 'https://contoh.test/88.pdf'],
        ]);
        $isi = $this->get(route('laporan.baru'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-uang="1"[^>]*>Informasi kerugian negara</', $isi);
        $this->assertMatchesRegularExpression('/data-uang="1"[^>]*>Kerugian daerah</', $isi);
        $this->assertDoesNotMatchRegularExpression('/<option[^>]*>Administratif</', $isi);
    }
}
