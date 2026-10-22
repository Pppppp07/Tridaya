<?php

namespace Tests\Feature;

use App\Enums\JenisReferensi;
use App\Models\AlertKeamanan;
use App\Models\KategoriTemuan;
use App\Models\LogAktivitas;
use App\Models\Referensi;
use App\Models\Satker;
use App\Models\Temuan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/** Data master: tidak ada yang dihapus, hanya dinonaktifkan. */
class DataMasterTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        $this->masuk('admin@contoh.test');
    }

    public function test_kategori_internal_bisa_ditambah_diganti_nama_dan_dipadamkan(): void
    {
        $this->post(route('master.tambah'), ['nama' => 'Kelebihan pembayaran'])->assertRedirect();
        $baru = Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)
            ->where('nama', 'Kelebihan pembayaran')->first();
        $this->assertNotNull($baru);
        $this->assertTrue($baru->aktif);

        /* Kiriman warna diabaikan (warna label dibuang). */
        $this->post(route('master.simpan', $baru), ['nama' => 'Kelebihan pembayaran belanja', 'warna' => 'merah'])
            ->assertRedirect();
        $baru->refresh();
        $this->assertSame('Kelebihan pembayaran belanja', $baru->nama);
        $this->assertArrayNotHasKey('warna', $baru->getAttributes());

        $this->post(route('master.saklar', $baru))->assertRedirect();
        $this->assertFalse($baru->fresh()->aktif);

        /* Ketiganya tercatat di Riwayat perubahan, lengkap sebelum → sesudah. */
        $log = LogAktivitas::where('aksi', 'like', 'master.intern.%')->orderBy('id')->get();
        $this->assertSame(['master.intern.tambah', 'master.intern.ubah', 'master.intern.saklar'], $log->pluck('aksi')->all());
        $this->assertSame(['nama' => 'Kelebihan pembayaran'], $log[1]->rincian['sebelum']);
        $this->assertSame(['aktif' => false], $log[2]->rincian['sesudah']);
        $this->get(route('master', ['tab' => 'riwayat']))->assertOk()
            ->assertSee('Mengubah nama kategori internal')->assertSee('Kelebihan pembayaran belanja');
    }

    /** Nama kategori internal yang dipakai temuan terkunci. */
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

    public function test_hanya_admin_dti_boleh_membuka_dan_mengelola_data_master(): void
    {
        $kategori = Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)->firstOrFail();
        $temuan = KategoriTemuan::firstOrFail();
        $unit = Satker::firstOrFail();
        $pengguna = $this->akun('uki@contoh.test');
        $semula = [$kategori->aktif, $temuan->aktif, $unit->aktif, $pengguna->aktif, $pengguna->peran];
        $jumlah = [Referensi::count(), KategoriTemuan::count(), Satker::count(), User::count()];

        /* Menu tersembunyi saja tidak cukup: alamat langsung, unduhan,
           direktori, dan kiriman harus menolak semua peran selain Admin DTI. */
        foreach (['setba@contoh.test', 'dti@contoh.test', 'uki@contoh.test',
            'inspektorat@contoh.test', 'pimpinan@contoh.test', 'medan'] as $akun) {
            $this->masuk($akun);
            foreach (['ringkasan', 'pengguna', 'peran', 'unit', 'temuan', 'riwayat'] as $tab) {
                $this->get(route('master', ['tab' => $tab]))->assertForbidden();
            }
            foreach ([
                route('master.pengguna.unduh'), route('master.unit.unduh'), route('master.riwayat.unduh'),
                route('master.daftar.unduh', ['daftar' => 'intern']),
                route('master.pengguna.cari', ['q' => 'siti']), route('master.unit.irm', $unit),
            ] as $alamat) {
                $this->get($alamat)->assertForbidden();
            }
            foreach ([
                [route('master.tambah'), ['nama' => 'Tidak boleh ditambahkan']],
                [route('master.saklar', $kategori), []],
                [route('master.temuan.tambah'), ['nama' => 'Ditolak', 'sumber' => 'LHP']],
                [route('master.temuan.saklar', $temuan), []],
                [route('master.unit.tambah'), ['nama' => 'Satuan kerja ditolak']],
                [route('master.unit.saklar', $unit), []],
                [route('master.pengguna.simpan', $pengguna), ['peran' => 'admin']],
                [route('master.pengguna.saklar', $pengguna), []],
                [route('master.pengguna.sesi', $pengguna), []],
                [route('master.pengguna.massal'), ['ids' => [$pengguna->id], 'aksi' => 'nonaktif']],
                [route('master.banyak', ['daftar' => 'intern']), ['nama' => "Ditolak satu\nDitolak dua"]],
                [route('master.gabung', ['daftar' => 'intern', 'id' => $kategori->id]), ['tujuan' => $kategori->id]],
            ] as [$alamat, $data]) {
                $this->post($alamat, $data)->assertForbidden();
            }
        }
        $this->assertSame($semula, [$kategori->fresh()->aktif, $temuan->fresh()->aktif,
            $unit->fresh()->aktif, $pengguna->fresh()->aktif, $pengguna->fresh()->peran]);
        $this->assertSame($jumlah, [Referensi::count(), KategoriTemuan::count(), Satker::count(), User::count()]);
    }

    /** Tiap daftar hanya bisa diubah lewat jalurnya sendiri. */
    public function test_butir_tidak_bisa_diubah_lewat_jalur_daftar_lain(): void
    {
        $bentuk = Referensi::where('jenis', JenisReferensi::BENTUK_TL->value)->firstOrFail();
        $sifat = Referensi::where('jenis', JenisReferensi::SIFAT_REKOM->value)->firstOrFail();
        $intern = Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)->firstOrFail();

        $this->post(route('master.simpan', $bentuk), ['nama' => 'Diubah diam-diam'])->assertForbidden();
        $this->post(route('master.saklar', $bentuk))->assertForbidden();
        $this->post(route('master.sifat.saklar', $bentuk))->assertForbidden();
        $this->post(route('master.bentuk.simpan', $sifat), ['nama' => 'Diubah diam-diam'])->assertForbidden();
        $this->post(route('master.bentuk.saklar', $intern))->assertForbidden();
    }

    public function test_bentuk_tindak_lanjut_diatur_di_data_master(): void
    {
        $this->get(route('master', ['tab' => 'bentuk']))->assertOk()
            ->assertSee('Bentuk tindak lanjut')->assertSee('Surat teguran')->assertSee('Dokumen usulan');

        $this->post(route('master.bentuk.tambah'), [
            'nama' => 'Pengembalian  kelebihan bayar', 'dokumen' => "Bukti setor\n\nRekap  pengembalian\nbukti setor",
        ])->assertRedirect();
        $baru = Referensi::where('jenis', JenisReferensi::BENTUK_TL->value)
            ->where('nama', 'Pengembalian kelebihan bayar')->firstOrFail();
        /* Baris kosong dan dokumen kembar dibuang. */
        $this->assertSame(['Bukti setor', 'Rekap pengembalian'], $baru->dokumen);
        $this->assertTrue($baru->aktif);
        $this->assertSame((int) Referensi::where('jenis', JenisReferensi::BENTUK_TL->value)->max('urutan'), $baru->urutan);
        $this->assertTrue(LogAktivitas::where('aksi', 'master.bentuk.tambah')->exists());

        $this->post(route('master.bentuk.tambah'), ['nama' => 'surat teguran'])->assertSessionHas('gagal');

        /* Sudah dipakai tindak lanjut: nama terkunci, dokumen usulannya tetap bisa diubah. */
        $dipakai = Referensi::where('jenis', JenisReferensi::BENTUK_TL->value)
            ->whereIn('id', \App\Models\Tindakan::whereNotNull('bentuk_id')->pluck('bentuk_id'))->firstOrFail();
        $this->post(route('master.bentuk.simpan', $dipakai), ['nama' => 'Ganti nama', 'dokumen' => 'X'])
            ->assertSessionHas('gagal');
        $this->assertNotSame('Ganti nama', $dipakai->fresh()->nama);
        $this->post(route('master.bentuk.simpan', $dipakai), ['nama' => $dipakai->nama, 'dokumen' => "Dokumen A\nDokumen B"])
            ->assertSessionMissing('gagal');
        $this->assertSame(['Dokumen A', 'Dokumen B'], $dipakai->fresh()->dokumen);
        $this->get(route('master', ['tab' => 'bentuk', 'jendela' => 'bentuk-'.$dipakai->id]))->assertOk()
            ->assertSee('namanya terkunci');

        /* Gabung: tindak lanjutnya pindah, asalnya dinonaktifkan. */
        $n = \App\Models\Tindakan::where('bentuk_id', $dipakai->id)->count();
        $this->post(route('master.gabung', ['daftar' => 'bentuk', 'id' => $dipakai->id]), ['ke' => $baru->id])
            ->assertSessionHas('pesan');
        $this->assertSame(0, \App\Models\Tindakan::where('bentuk_id', $dipakai->id)->count());
        $this->assertSame($n, \App\Models\Tindakan::where('bentuk_id', $baru->id)->count());
        $this->assertFalse($dipakai->fresh()->aktif);
    }

    public function test_paling_tidak_satu_bentuk_tetap_aktif(): void
    {
        $semua = Referensi::where('jenis', JenisReferensi::BENTUK_TL->value)->where('aktif', true)->orderBy('id')->get();
        $terakhir = $semua->pop();
        Referensi::whereIn('id', $semua->pluck('id'))->update(['aktif' => false]);

        $this->post(route('master.bentuk.saklar', $terakhir))->assertSessionHas('gagal');
        $this->assertTrue($terakhir->fresh()->aktif);
    }

    /**
     * Enam area kerja utama, termasuk kebijakan sumber bukti. Panduan peran
     * tetap menjadi bagian User & akses.
     */
    public function test_halaman_data_master_bertab_dan_jendelanya_dari_alamat(): void
    {
        $isi = $this->get(route('master'))->assertOk()->getContent();
        foreach (['Dashboard', 'User &amp; akses', 'Satuan kerja', 'Kategori &amp; referensi', 'Log admin'] as $judul) {
            $this->assertMatchesRegularExpression('/<a role="tab"[^>]*>(?:(?!<\/a>).)*'.preg_quote($judul, '/').'/s', $isi);
        }
        $this->assertSame(6, substr_count($isi, '<a role="tab"'));
        $this->assertMatchesRegularExpression('/<a role="tab"[^>]*aria-selected="true"[^>]*>(?:(?!<\/a>).)*Dashboard/s', $isi);
        $this->assertStringContainsString('<h2>Dashboard', $isi);

        foreach (['pengguna' => 'User &amp; akses', 'unit' => 'Satuan kerja', 'temuan' => 'Kategori temuan',
            'intern' => 'Kategori internal', 'sifat' => 'Sifat rekomendasi', 'riwayat' => 'Log admin'] as $tab => $judul) {
            $this->get(route('master', ['tab' => $tab]))->assertOk()->assertSee('<h2>'.$judul, false);
        }
        $panduan = $this->get(route('master', ['tab' => 'peran']))->assertOk()
            ->assertSee('Panduan peran')->assertSee('Hak akses tiap peran mengikuti alur kerja. Atur peran user melalui Daftar user.')
            ->assertSee('aria-labelledby="dm-tab-pengguna"', false)->getContent();
        $this->assertMatchesRegularExpression('/<a role="tab"[^>]*id="dm-tab-pengguna"[^>]*aria-selected="true"/s', $panduan);
        $this->assertStringNotContainsString('id="dm-tab-peran"', $panduan);
        /* Alamat lama (?sorot=m-sifat) tetap mendarat di tabnya. */
        $this->get(route('master', ['sorot' => 'm-sifat']))->assertOk()->assertSee('<h2>Sifat rekomendasi', false);

        /* Jendela Tambah digambar server — jalan tanpa skrip. */
        $this->get(route('master', ['tab' => 'unit', 'jendela' => 'unit']))->assertOk()
            ->assertSee('data-tirai-master', false)->assertSee('Tambah satuan kerja')->assertSee('wajib diisi');
        /* Warna kategori tidak ada lagi. */
        $this->get(route('master', ['tab' => 'intern']))->assertOk()->assertDontSee('Warna label');
    }

    public function test_preview_log_ringkas_tidak_membatasi_riwayat_atau_ekspor(): void
    {
        $ids = [];
        for ($i = 1; $i <= 5; $i++) {
            $ids[] = LogAktivitas::create([
                'waktu' => now()->addDays(1), 'kelompok' => 'master', 'aksi' => 'master.unit.ubah',
                'ringkasan' => 'Preview-admin-uji '.$i, 'nama' => 'Admin penguji', 'peran' => 'admin',
            ])->id;
        }
        LogAktivitas::create([
            'waktu' => now()->addDays(2), 'kelompok' => 'berkas', 'aksi' => 'berkas',
            'ringkasan' => 'Berkas di luar Data Master',
        ]);

        $this->get(route('master'))->assertOk()
            ->assertViewHas('terbaru', fn ($log) => $log->pluck('id')->all() === array_reverse(array_slice($ids, -3)))
            ->assertSee('3 terakhir · Semua waktu')
            ->assertDontSee('Preview-admin-uji 1')->assertDontSee('Preview-admin-uji 2')
            ->assertDontSee('Berkas di luar Data Master');

        $alamat = ['tab' => 'riwayat', 'cari' => 'Preview-admin-uji'];
        $logLengkap = $this->get(route('master', $alamat))->assertOk()
            ->assertViewHas('jumlahRiwayat', 5)->assertSee('Preview-admin-uji 1');
        foreach ($ids as $id) {
            $logLengkap->assertSee(route('log', ['kejadian' => $id, 'rinci' => $id]));
        }
        $csv = $this->get(route('master.riwayat.unduh', ['cari' => 'Preview-admin-uji']))->assertOk()->streamedContent();
        for ($i = 1; $i <= 5; $i++) {
            $this->assertStringContainsString('Preview-admin-uji '.$i, $csv);
        }
    }

    public function test_detail_log_admin_tetap_membuka_bukti_dan_perubahan_asli(): void
    {
        $rincian = [
            'sebelum' => ['nama' => 'Kategori lama khusus detail'],
            'sesudah' => ['nama' => 'Kategori baru khusus detail'],
            'konteks' => ['sumber' => 'web', 'metode' => 'POST', 'alamat' => '/data-master/intern'],
        ];
        $log = LogAktivitas::create([
            'waktu' => now()->addDays(1), 'kelompok' => 'master', 'aksi' => 'master.intern.ubah',
            'nama' => 'Admin saat kejadian', 'peran' => 'admin',
            'ringkasan' => 'Mengubah kategori untuk pemeriksaan detail',
            'ip' => '192.0.2.42', 'rincian' => $rincian,
        ]);
        $alamat = route('log', ['kejadian' => $log->id, 'rinci' => $log->id]);
        foreach ([[], ['tab' => 'riwayat']] as $tab) {
            $this->get(route('master', $tab))->assertOk()->assertSee($alamat)
                ->assertSee('Mengubah kategori untuk pemeriksaan detail')
                ->assertDontSee('Kategori lama khusus detail')->assertDontSee('192.0.2.42');
        }
        $isi = $this->get($alamat)->assertOk()->assertSee('Kategori lama khusus detail')
            ->assertSee('Kategori baru khusus detail')->assertSee('192.0.2.42')
            ->assertSee('Peran pada saat kejadian')->assertSee('/data-master/intern')->getContent();
        $this->assertStringNotContainsString('id="lg-r-'.$log->id.'" hidden', $isi);
        $this->assertSame($rincian, $log->fresh()->rincian);
    }

    public function test_dashboard_ringkas_tetap_menampilkan_kekosongan_pj_dan_alert_terbuka(): void
    {
        Satker::create(['kode' => 'DM-PERHATIAN', 'nama' => 'Satuan kerja uji tanpa PJ', 'aktif' => true]);
        foreach (['baru', 'perlu_tindak_lanjut', 'wajar', 'selesai'] as $status) {
            AlertKeamanan::create([
                'aturan' => 'perubahan_peran', 'tingkat' => 'tinggi', 'judul' => 'Alert uji '.$status,
                'alasan' => 'Indikasi yang memerlukan pemeriksaan.', 'status' => $status,
                'pertama_pada' => now(), 'terakhir_pada' => now(),
                'sumber' => 'web', 'cakupan' => 'uji-compact-'.$status, 'bukti' => [],
            ]);
        }
        $this->get(route('master'))->assertOk()->assertViewHas('jumlahAlert', 2)
            ->assertSee('1 satuan kerja aktif belum punya penanggung jawab')
            ->assertSee(route('master', ['tab' => 'unit', 'filter' => 'tanpa-pj']))
            ->assertSee('Perlu ditinjau.')
            ->assertSee(route('log', ['tab' => 'alert', 'status' => 'terbuka']))
            ->assertSee('bukan bukti pelanggaran')
            ->assertDontSee('Peran &amp; penanggung jawab lengkap');
    }

    /**
     * Sifat baru membawa keterangan menuntut penyetoran atau tidak; keduanya terkunci begitu
     * dipakai.
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

        /* Admin mengelola referensi, sedangkan pencatatan laporan tetap
           menjadi pekerjaan Setba. */
        $this->masuk('setba@contoh.test');
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
