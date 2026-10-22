<?php

namespace Tests\Feature;

use App\Enums\PosisiBerkas;
use App\Http\Controllers\LaporanBaruController;
use App\Models\DrafLaporan;
use App\Models\KategoriTemuan;
use App\Models\LogAktivitas;
use App\Support\Rangka;
use App\Models\Lampiran;
use App\Models\Laporan;
use App\Models\Notifikasi;
use App\Models\Referensi;
use App\Models\Satker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/** Catat laporan baru: isi dan tinjau, draf yang tidak hilang, dan pengajuan. */
class CatatLaporanBaruTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        $this->masuk('setba@contoh.test');
    }

    private function draf(): array
    {
        return DrafLaporan::where('user_id', auth()->id())->firstOrFail()->isian;
    }

    private function isiSurat(): void
    {
        $this->post(route('laporan.baru.simpan'), [
            'aksi' => 'simpan',
            'surat' => ['sumber' => 'LHP', 'nomor' => '77/LHP/XVIII/09/2026',
                'tgl_surat' => '2026-08-01', 'tgl_terima' => '2026-08-05'],
            'berkas' => ['judul' => 'Laporan Hasil Pemeriksaan 77/LHP/XVIII/09/2026',
                'link' => 'https://contoh.test/77.pdf'],
        ])->assertRedirect(route('laporan.baru'));
    }

    /**
     * Isian temuan pertama seperti terkirim dari halaman isian (tem[id][…]); `$ubah` menimpa
     * sebagian isian tindak lanjut pertamanya.
     */
    private function isianTemuan(array $ubah = []): array
    {
        $d = $this->draf();
        $t = $d['temuan'][0];
        $r = $t['rekom'][0];
        $tk = $r['tindakan'][0];

        $medan = Satker::where('nama_pendek', 'Balai Wil. I Medan')->firstOrFail();
        $poli = Satker::where('nama_pendek', 'Politeknik PU')->firstOrFail();
        $kategori = KategoriTemuan::where('sumber', 'LHP')->where('nama', 'Kepatuhan')->firstOrFail();
        $kerugian = Referensi::where('jenis', 'sifat_rekom')->where('nama', 'Informasi kerugian negara')->firstOrFail();

        return ['tem' => [$t['id'] => [
            'nomor' => '1.1',
            'judul' => 'Honorarium Narasumber Dibayarkan Melebihi Standar Biaya',
            'sebab' => 'Verifikasi SPJ tidak membandingkan dengan standar biaya masukan.',
            'akibat' => 'Kelebihan pembayaran honorarium yang membebani negara.',
            'kategori' => (string) $kategori->id,
            'intern' => $t['intern'],
            'satker' => [$medan->id, $poli->id],
            'rekom' => [
                $r['id'] => [
                    'uraian' => 'Menteri PU agar memerintahkan Kepala BPSDM menarik kelebihan pembayaran dan menyetorkannya ke kas negara.',
                    'ref_lhp' => 'I.1.1.a',
                    'sifat' => (string) $kerugian->id,
                    'tindakan' => [
                        $tk['id'] => array_replace([
                            'bentuk' => 'Penyetoran ke kas negara',
                            'satker' => [$medan->id, $poli->id],
                            'nilai' => [$medan->id => '7500000', $poli->id => '12500000'],
                            'dokumen' => ['Bukti setor ke kas negara (SSBP)', 'Nota Konfirmasi KPPN'],
                            'tgl_renaksi' => '2026-10-04',
                            'target' => '',
                            'catatan' => 'Lampirkan bukti setor dan NTPN.',
                        ], $ubah),
                    ],
                ],
            ],
        ]]];
    }

    /** Isian halaman isian, berikut tombolnya. */
    private function isiTemuan(string $aksi = 'maju'): void
    {
        $this->post(route('laporan.baru.simpan'), ['aksi' => $aksi] + $this->isianTemuan())
            ->assertRedirect(route('laporan.baru'));
    }

    public function test_laporan_baru_pecah_jadi_penugasan_tiap_satuan_kerja(): void
    {
        $sebelum = Laporan::count();

        $this->isiSurat();
        $this->isiTemuan();
        $this->assertSame(2, $this->draf()['n']);
        $this->post(route('laporan.baru.simpan'), ['aksi' => 'ajukan'])
            ->assertRedirect();

        $lap = Laporan::where('nomor', '77/LHP/XVIII/09/2026')->first();
        $this->assertNotNull($lap);
        $this->assertSame($sebelum + 1, Laporan::count());

        $tem = $lap->temuan->first();
        $this->assertSame('1.1', $tem->nomor_pada_surat);
        $this->assertCount(2, $tem->satkers);

        /* Kodenya Ref IDT: tahun LHP . nomor pendek suratnya . Ref LHP. */
        $rek = $tem->rekomendasi->first();
        $this->assertSame('2026.77.I.1.1.a', $rek->kode);
        $this->assertSame(20000000, (int) $rek->nilai_pulih);
        $this->assertSame('2026-10-04', $rek->tenggat_jawab->toDateString());

        /* Satu tindak lanjut, dua penugasan — masing-masing dengan bagiannya. */
        $baris = $rek->daftarSasaran();
        $this->assertCount(2, $baris);
        foreach ($baris as $x) {
            $this->assertSame(PosisiBerkas::SATKER, $x->pos());
            /* Dokumen yang diminta berdiri sendiri untuk tiap satuan kerja. */
            $this->assertCount(2, $x->permintaanDokumen->first()->item);
        }

        /* Surat pemeriksaannya melekat pada laporan dan pada rekomendasinya,
           dan keduanya bertanda surat asli. */
        $this->assertTrue(Lampiran::where('laporan_id', $lap->id)->where('surat_asli', true)->exists());
        $this->assertTrue(Lampiran::where('rekomendasi_id', $rek->id)->where('surat_asli', true)->exists());

        /* Tiap satuan kerja diberi tahu bebannya sendiri. */
        $pemberitahuan = Notifikasi::where('rekomendasi_id', $rek->id)->get();
        $this->assertCount(2, $pemberitahuan);
        foreach ($pemberitahuan as $k) {
            $this->assertStringContainsString('Rekomendasi baru untuk Anda', $k->aksi);
            $this->assertSame('r-kepala', $k->blok);
            $this->assertCount(1, $k->satker);
        }

        $this->assertStringContainsString('Rekomendasi dikirim ke', $rek->riwayat->last()->aksi);

        /* Drafnya habis begitu terkirim. */
        $this->assertSame(0, DrafLaporan::count());
    }

    public function test_halaman_isian_satu_halaman_dengan_tab(): void
    {
        $html = $this->get(route('laporan.baru'))->assertOk()
            /* Dua langkah, bukan tiga. */
            ->assertSee('Isi laporan')->assertSee('Tinjau &amp; kirim', false)
            /* Isian wajib berbintang, dijelaskan sekali — tanpa tulisan "tidak wajib". */
            ->assertSee('wajib diisi')->assertDontSee('tidak wajib')
            /* Tombol Tambah di ujung tiap deret tab. */
            ->assertSee('Tambah temuan')->assertSee('Tambah rekomendasi')->assertSee('Tambah tindak lanjut')
            ->assertSee('role="tablist"', false)
            ->getContent();

        /* Enter di isian satu baris hanya menyimpan, bukan "Kembali ke beranda". */
        $this->assertLessThan(strpos($html, 'value="tinggalkan"'), strpos($html, 'value="simpan"'));
    }

    public function test_tab_terpilih_dibawa_kiriman_berikutnya(): void
    {
        $this->isiSurat();
        $pertama = $this->draf()['temuan'][0]['id'];

        /* Temuan baru langsung terpilih, dan alamatnya menunjuk panelnya. */
        $jawab = $this->post(route('laporan.baru.simpan'), ['aksi' => 'tambah-temuan']);
        $kedua = $this->draf()['temuan'][1]['id'];
        $jawab->assertRedirect(route('laporan.baru').'#fb-tem-'.$kedua);
        $this->assertSame($kedua, $this->draf()['aktif']);

        /* Tab yang dipilih skrip (tanpa mengirim) ikut kiriman berikutnya. */
        $this->post(route('laporan.baru.simpan'), ['aksi' => 'simpan', 'ui' => ['aktif' => $pertama, 'buka' => '']])
            ->assertRedirect(route('laporan.baru'));
        $this->assertSame($pertama, $this->draf()['aktif']);

        /* Seluruh panel dirender; yang tidak terpilih disembunyikan. */
        $html = $this->get(route('laporan.baru'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/id="fb-tab-tem-'.$pertama.'"\s+aria-selected="true"/', $html);
        $this->assertMatchesRegularExpression('/id="fb-tab-tem-'.$kedua.'"\s+aria-selected="false"/', $html);
        $this->assertMatchesRegularExpression('/id="fb-tem-'.$kedua.'"[^>]*\s+hidden\s/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="fb-tem-'.$pertama.'"[^>]*\s+hidden\s/', $html);
    }

    public function test_tambah_dan_hapus_tindak_lanjut_memilih_tabnya(): void
    {
        $this->isiSurat();
        $this->isiTemuan('simpan');
        $d = $this->draf();
        $t = $d['temuan'][0]['id'];
        $r = $d['temuan'][0]['rekom'][0]['id'];

        $this->post(route('laporan.baru.simpan'), ['aksi' => "tambah-tindakan:$t:$r"])->assertRedirect(route('laporan.baru'));
        $d = $this->draf();
        $tl = $d['temuan'][0]['rekom'][0]['tindakan'];
        $this->assertCount(2, $tl);
        /* Yang baru langsung terpilih, dan tanggalnya ikut yang pertama. */
        $this->assertSame(1, $d['tl'][$r]);
        $this->assertSame($tl[0]['tgl_renaksi'], $tl[1]['tgl_renaksi']);

        $this->post(route('laporan.baru.simpan'), ['aksi' => "hapus-tindakan:$t:$r:1"])->assertRedirect(route('laporan.baru'));
        $d = $this->draf();
        $this->assertCount(1, $d['temuan'][0]['rekom'][0]['tindakan']);
        $this->assertSame(0, $d['tl'][$r]);
    }

    public function test_tunjukkan_membuka_bagian_yang_kurang(): void
    {
        $this->isiSurat();
        /* Satuan kerja tindak lanjutnya belum dipilih. */
        $this->post(route('laporan.baru.simpan'), ['aksi' => 'simpan'] + $this->isianTemuan(['satker' => []]))
            ->assertRedirect(route('laporan.baru'));
        $d = $this->draf();
        $t = $d['temuan'][0]['id'];
        $r = $d['temuan'][0]['rekom'][0]['id'];

        $this->get(route('laporan.baru'))->assertOk()
            ->assertSee('Temuan 1 belum lengkap: satuan kerja di rekomendasi a')
            ->assertSee('Tunjukkan');

        /* "Tunjukkan" membuka bagian yang kurang, alamatnya menunjuk panel itu, kursor ditaruh
           di sana. */
        $this->post(route('laporan.baru.simpan'), ['aksi' => 'tunjukkan'])
            ->assertRedirect(route('laporan.baru').'#fb-tl-'.$r.'-0')
            ->assertSessionHas('fokus', true);
        $d = $this->draf();
        $this->assertSame($t, $d['aktif']);
        $this->assertSame($r, $d['buka']);
        $this->assertSame(0, $d['tl'][$r]);
        $this->assertArrayNotHasKey('_sorot', $d);

        $this->get(route('laporan.baru'))->assertOk()->assertSee('data-fokus', false);
    }

    public function test_langkah_tidak_maju_kalau_isiannya_kurang(): void
    {
        /* Tombolnya tidak dimatikan — server yang menahan, sambil membuka
           bagian yang kurang seperti "Tunjukkan". */
        $this->post(route('laporan.baru.simpan'), [
            'aksi' => 'maju',
            'surat' => ['sumber' => 'LHP', 'nomor' => '', 'tgl_surat' => '', 'tgl_terima' => ''],
        ])->assertRedirect(route('laporan.baru').'#fb-surat')
            ->assertSessionHas('gagal')->assertSessionHas('fokus', true);

        $d = $this->draf();
        $this->assertSame(1, $d['n']);
        $this->assertTrue($d['surat_buka']);
    }

    public function test_draf_tersimpan_dan_dilanjutkan_di_halaman_isian(): void
    {
        $this->isiSurat();
        $this->isiTemuan();
        $this->assertSame(2, $this->draf()['n']);

        $this->post(route('laporan.baru.simpan'), ['aksi' => 'simpan-draf'])
            ->assertRedirect(route('rekomendasi.index'));

        $this->assertSame(1, DrafLaporan::count());

        /* Tombol di batang atas berganti jadi "Lanjutkan draf laporan". */
        $this->get(route('rekomendasi.index'))->assertOk()->assertSee('Lanjutkan draf laporan');

        $this->get(route('laporan.baru'))->assertOk()
            ->assertSee('Melanjutkan draf yang disimpan');

        /* Draf dilanjutkan di halaman isian dengan isian utuh. */
        $d = $this->draf();
        $this->assertSame('77/LHP/XVIII/09/2026', $d['surat']['nomor']);
        $this->assertSame('Honorarium Narasumber Dibayarkan Melebihi Standar Biaya', $d['temuan'][0]['judul']);
        $this->assertSame(1, $d['n']);
        $this->assertNull($d['surat_buka']);
    }

    public function test_draf_formulir_tiga_langkah_dibuka_di_langkah_sepadan(): void
    {
        $this->isiSurat();
        $this->isiTemuan('simpan');

        /* Draf yang tersimpan sebelum formulir jadi dua langkah. */
        $lama = $this->draf();
        unset($lama['versi'], $lama['tl'], $lama['surat_buka']);
        $baris = DrafLaporan::where('user_id', auth()->id())->firstOrFail();

        $baris->update(['isian' => ['n' => 3] + $lama]);
        $this->get(route('laporan.baru'))->assertOk()
            ->assertSee('Kembali mengisi')->assertSee('Ajukan laporan');

        $baris->update(['isian' => ['n' => 2] + $lama]);
        $this->get(route('laporan.baru'))->assertOk()
            ->assertSee('wajib diisi')->assertDontSee('Kembali mengisi');
    }

    public function test_tab_rekomendasi_memuat_inti_uraiannya(): void
    {
        $f = app(LaporanBaruController::class);
        $this->assertSame('Menyetorkan PNBP', $f->intiUraian('Menteri PU agar memerintahkan Kepala BPSDM menyetorkan PNBP'));
        $this->assertSame('Menyusun SOP', $f->intiUraian('Sekretaris Badan agar memerintahkan para kepala balai untuk menyusun SOP'));
        $this->assertSame('Memberi sanksi', $f->intiUraian('Kepala BPSDM agar memberi sanksi'));
        $this->assertSame('Menyetor sisa dana', $f->intiUraian('Menyetor sisa dana'));
        $this->assertSame('Menteri PU agar', $f->intiUraian('Menteri PU agar'));

        $this->isiSurat();
        $this->isiTemuan('simpan');
        $this->get(route('laporan.baru'))->assertOk()
            ->assertSee('Menarik kelebihan pembayaran dan menyetorkannya ke kas negara.');
    }

    /** Formulir kosong bukan draf. */
    public function test_formulir_kosong_yang_ditinggalkan_bukan_draf(): void
    {
        /* Langkah di tengah formulir kosong menyimpan keadaan kerjanya, tapi itu
           bukan draf: tombolnya tetap "Catat laporan baru". */
        $this->post(route('laporan.baru.simpan'), ['aksi' => 'simpan'])->assertRedirect();
        Rangka::lupakan();
        $this->get(route('rekomendasi.index'))->assertOk()
            ->assertSee('Catat laporan baru')->assertDontSee('Lanjutkan draf laporan');

        /* Kembali ke beranda dari formulir kosong: tidak ada draf, tidak ada log. */
        $this->post(route('laporan.baru.simpan'), ['aksi' => 'tinggalkan'])->assertRedirect(route('rekomendasi.index'));
        $this->assertSame(0, DrafLaporan::count());
        $this->assertFalse(LogAktivitas::where('aksi', 'laporan.draf')->exists());

        /* Sisa draf kosong lama dibuang saat formulir dibuka. */
        DrafLaporan::create(['user_id' => auth()->id(), 'isian' => ['ditinggal' => true, 'disimpan' => '2026-08-17', 'versi' => 2]]);
        Rangka::lupakan();
        $this->get(route('rekomendasi.index'))->assertOk()
            ->assertSee('Catat laporan baru')->assertDontSee('Lanjutkan draf laporan');
        Rangka::lupakan();
        $this->get(route('laporan.baru'))->assertOk()->assertDontSee('Melanjutkan draf yang disimpan');
        $this->assertSame(0, DrafLaporan::count());

        /* Yang berisi tetap draf, lengkap dengan tombol Kosongkan formulir. */
        $this->isiSurat();
        $this->post(route('laporan.baru.simpan'), ['aksi' => 'tinggalkan'])->assertRedirect(route('rekomendasi.index'));
        $this->assertSame(1, DrafLaporan::count());
        Rangka::lupakan();
        $this->get(route('rekomendasi.index'))->assertOk()->assertSee('Lanjutkan draf laporan');
        Rangka::lupakan();
        $this->get(route('laporan.baru'))->assertOk()
            ->assertSee('Melanjutkan draf yang disimpan')->assertSee('Kosongkan formulir');
    }

    public function test_kosongkan_membuang_drafnya(): void
    {
        $this->isiSurat();
        $this->assertSame(1, DrafLaporan::count());

        $this->post(route('laporan.baru.simpan'), ['aksi' => 'kosongkan'])
            ->assertRedirect(route('laporan.baru'));

        $this->assertSame(0, DrafLaporan::count());
    }

    public function test_nomor_surat_yang_sudah_pernah_dicatat_ditolak(): void
    {
        $nomor = Laporan::first()->nomor;

        $this->isiSurat();
        $this->isiTemuan();

        /* Nomornya diganti jadi nomor laporan yang sudah ada. */
        $this->post(route('laporan.baru.simpan'), [
            'aksi' => 'ajukan',
            'surat' => ['sumber' => 'LHP', 'nomor' => $nomor,
                'tgl_surat' => '2026-08-01', 'tgl_terima' => '2026-08-05'],
        ])->assertRedirect(route('laporan.baru'))->assertSessionHas('gagal');

        $this->assertSame(1, Laporan::where('nomor', $nomor)->count());
    }

    public function test_bukan_setba_tidak_boleh_mencatat(): void
    {
        $this->masuk('medan')
            ->post(route('laporan.baru.simpan'), ['aksi' => 'maju'])
            ->assertForbidden();
    }

    /* ---- Kiriman dari skrip: ditukar di tempat ---- */

    /** Tanda yang dikirim simtlhp.js (kirimLatar) bersama tiap kirimannya. */
    private const SKRIP = ['X-Requested-With' => 'XMLHttpRequest'];

    public function test_kiriman_skrip_dijawab_formulirnya_tanpa_pengalihan(): void
    {
        $this->isiSurat();

        $jawab = $this->post(route('laporan.baru.simpan'), ['aksi' => 'tambah-temuan'], self::SKRIP)
            ->assertOk()->assertSee('data-form-baru', false);
        $kedua = $this->draf()['temuan'][1]['id'];
        /* Jawaban tanpa pengalihan tidak punya jangkar alamat: bagian yang
           dituju dibawa formulirnya sendiri. */
        $jawab->assertSee('data-gulir="fb-tem-'.$kedua.'"', false);
        $this->assertSame($kedua, $this->draf()['aktif']);
    }

    public function test_kiriman_skrip_yang_ditahan_membawa_pesan_dan_fokusnya(): void
    {
        $this->post(route('laporan.baru.simpan'), [
            'aksi' => 'maju',
            'surat' => ['sumber' => 'LHP', 'nomor' => '', 'tgl_surat' => '', 'tgl_terima' => ''],
        ], self::SKRIP)->assertOk()
            ->assertSee('class="pesan bad"', false)
            ->assertSee('data-fokus', false)
            ->assertSee('data-gulir="fb-surat"', false);
        $this->assertSame(1, $this->draf()['n']);

        /* Keterangan sesaatnya tidak tertinggal untuk halaman berikutnya. */
        Rangka::lupakan();
        $this->get(route('laporan.baru'))->assertOk()
            ->assertDontSee('class="pesan bad"', false)->assertDontSee('data-fokus', false);
    }

    public function test_kosongkan_lewat_skrip_menjawab_formulir_kosong(): void
    {
        $this->isiSurat();

        $this->post(route('laporan.baru.simpan'), ['aksi' => 'kosongkan'], self::SKRIP)
            ->assertOk()->assertSee('data-form-baru', false)->assertDontSee('77/LHP/XVIII/09/2026');
        $this->assertSame(0, DrafLaporan::count());
    }

    /** Formulir baru tanpa draf: isian temuan pada kiriman pertama tidak terbuang. */
    public function test_isian_temuan_pada_kiriman_pertama_tidak_terbuang(): void
    {
        $this->get(route('laporan.baru'))->assertOk()->assertSee('name="tem[t0][satker][]"', false);

        $medan = Satker::where('nama_pendek', 'Balai Wil. I Medan')->firstOrFail();
        $this->post(route('laporan.baru.simpan'), [
            'tem' => ['t0' => ['judul' => 'Honorarium melebihi standar biaya', 'satker' => [$medan->id]]],
        ], self::SKRIP)->assertOk();

        $t = $this->draf()['temuan'][0];
        $this->assertSame('t0', $t['id']);
        $this->assertSame('Honorarium melebihi standar biaya', $t['judul']);
        $this->assertSame([$medan->id], $t['satker']);
    }

    /** Halaman yang diminta skrip untuk ditukar sebagian tidak menghabiskan pop-up pemberitahuan. */
    public function test_kiriman_skrip_tidak_menghabiskan_popup_pemberitahuan(): void
    {
        $this->post(route('laporan.baru.simpan'), ['aksi' => 'simpan'], self::SKRIP)
            ->assertOk()->assertDontSee('data-popup', false);

        Rangka::lupakan();
        $this->get(route('rekomendasi.index'))->assertOk()->assertSee('data-popup', false);
    }

    /** Bentuk dipilih dari Data Master; yang diketik bebas tidak lagi menambah daftarnya. */
    public function test_bentuk_tindak_lanjut_dipilih_dari_data_master(): void
    {
        $bentuk = fn (string $nama) => Referensi::where('jenis', 'bentuk_tl')->where('nama', $nama)->firstOrFail();
        $bentuk('Sosialisasi')->update(['aktif' => false]);
        $teguran = $bentuk('Surat teguran');
        $teguran->update(['dokumen' => ['Surat teguran bernomor', 'Bukti penyampaian']]);

        $this->isiSurat();
        $isi = $this->get(route('laporan.baru'))->assertOk()->getContent();
        $this->assertStringNotContainsString('<datalist id="bentuk-tl"', $isi);
        $this->assertMatchesRegularExpression('/<select[^>]*name="tem\[[^\]]+\]\[rekom\]\[[^\]]+\]\[tindakan\]\[[^\]]+\]\[bentuk\]"/', $isi);
        $this->assertStringContainsString('>Surat teguran</option>', $isi);
        $this->assertStringNotContainsString('>Sosialisasi</option>', $isi);

        /* Tindak lanjut baru: bentuk aktif pertama beserta dokumen usulannya dari Data Master. */
        $d = $this->draf();
        $tk = $d['temuan'][0]['rekom'][0]['tindakan'][0];
        $this->assertSame((string) $teguran->id, $tk['bentuk']);
        $this->assertSame(['Surat teguran bernomor', 'Bukti penyampaian'], $tk['dokumen']);

        /* Ganti bentuk: dokumen usulan yang belum disunting ikut berganti. */
        $setor = $bentuk('Bukti setor ke kas negara');
        $kirimTl = fn (array $tl) => $this->post(route('laporan.baru.simpan'), ['aksi' => 'simpan', 'tem' => [$d['temuan'][0]['id'] => [
            'rekom' => [$d['temuan'][0]['rekom'][0]['id'] => ['tindakan' => [$tk['id'] => $tl]]]]]]);
        $kirimTl(['bentuk' => (string) $setor->id, 'dokumen' => $tk['dokumen']]);
        $tk2 = $this->draf()['temuan'][0]['rekom'][0]['tindakan'][0];
        $this->assertSame((string) $setor->id, $tk2['bentuk']);
        $this->assertSame($setor->dokumen, $tk2['dokumen']);

        /* Ketikan bebas tidak dikenal: bentuknya kosong dan Data Master tidak bertambah. */
        $jumlah = Referensi::count();
        $kirimTl(['bentuk' => 'Bentuk karangan sendiri', 'dokumen' => $tk2['dokumen']]);
        $this->assertSame('', $this->draf()['temuan'][0]['rekom'][0]['tindakan'][0]['bentuk']);
        $this->assertSame($jumlah, Referensi::count());
    }

    /** Ref LHP terisi dari Nomor di surat temuannya, sampai disunting atau dikosongkan. */
    public function test_ref_lhp_terisi_dari_nomor_temuan_sampai_disunting(): void
    {
        $this->isiSurat();
        $d = $this->draf();
        $tid = $d['temuan'][0]['id'];
        $kirim = fn (array $tem) => $this->post(route('laporan.baru.simpan'), ['aksi' => 'simpan', 'tem' => [$tid => $tem]]);
        $ref = fn () => array_column($this->draf()['temuan'][0]['rekom'], 'ref_lhp');

        $kirim(['nomor' => '2.3', 'rekom' => [$d['temuan'][0]['rekom'][0]['id'] => ['ref_lhp' => '']]]);
        $this->assertSame(['2.3.a'], $ref());

        /* Rekomendasi berikutnya mendapat huruf berikutnya. */
        $this->post(route('laporan.baru.simpan'), ['aksi' => 'tambah-rek:'.$tid]);
        $this->assertSame(['2.3.a', '2.3.b'], $ref());

        /* Nomor berubah: yang otomatis ikut, yang disunting tetap. */
        [$r1, $r2] = array_column($this->draf()['temuan'][0]['rekom'], 'id');
        $kirim(['nomor' => '2.4', 'rekom' => [$r1 => ['ref_lhp' => '2.3.a'], $r2 => ['ref_lhp' => 'II.4.4.f']]]);
        $this->assertSame(['2.4.a', 'II.4.4.f'], $ref());

        /* Dikosongkan juga dihormati: tidak diisi lagi. */
        $kirim(['nomor' => '2.5', 'rekom' => [$r1 => ['ref_lhp' => ''], $r2 => ['ref_lhp' => 'II.4.4.f']]]);
        $this->assertSame(['', 'II.4.4.f'], $ref());
        $this->assertStringNotContainsString('data-ref-otomatis="1"', $this->get(route('laporan.baru'))->getContent());
    }

    /** Isian yang dikosongkan tersimpan kosong; isian yang tidak dikirim tetap. */
    public function test_isian_yang_dikosongkan_tersimpan_kosong(): void
    {
        $this->isiSurat();
        $this->isiTemuan('simpan');
        $d = $this->draf();
        $t = $d['temuan'][0];
        $r = $t['rekom'][0];
        $tk = $r['tindakan'][0];
        $medan = Satker::where('nama_pendek', 'Balai Wil. I Medan')->firstOrFail();
        $poli = Satker::where('nama_pendek', 'Politeknik PU')->firstOrFail();

        $this->post(route('laporan.baru.simpan'), [
            'aksi' => 'simpan',
            'surat' => ['nomor' => ''],
            'tem' => [$t['id'] => [
                'judul' => '', 'sebab' => '', 'satker' => $t['satker'],
                'rekom' => [$r['id'] => [
                    'uraian' => '',
                    'tindakan' => [$tk['id'] => [
                        'catatan' => '', 'target' => '', 'satker' => array_column($tk['satker'], 'satker'),
                        'nilai' => [$medan->id => ''],
                    ]],
                ]],
            ]],
        ])->assertRedirect(route('laporan.baru'));

        $d = $this->draf();
        $t2 = $d['temuan'][0];
        $tk2 = $t2['rekom'][0]['tindakan'][0];
        $nilai = collect($tk2['satker'])->pluck('nilai', 'satker');
        $this->assertSame('', $d['surat']['nomor']);
        $this->assertSame('', $t2['judul']);
        $this->assertSame('', $t2['sebab']);
        $this->assertSame('', $t2['rekom'][0]['uraian']);
        $this->assertSame('', $tk2['catatan']);
        $this->assertSame('', $tk2['target']);
        $this->assertSame('', $nilai[$medan->id]);

        /* Yang tidak dikirim tetap seperti tersimpan. */
        $this->assertSame('2026-08-01', $d['surat']['tgl_surat']);
        $this->assertSame($t['akibat'], $t2['akibat']);
        $this->assertSame('12500000', $nilai[$poli->id]);
    }
}
