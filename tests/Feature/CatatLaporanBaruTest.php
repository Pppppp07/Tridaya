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

/**
 * Mencatat laporan baru: dua langkah — isi laporan di satu halaman dengan
 * temuan, rekomendasi, dan tindak lanjut sebagai tab, lalu tinjau dan kirim —
 * draf yang tidak hilang, dan satu laporan yang pecah jadi penugasan terpisah
 * untuk tiap satuan kerja.
 */
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
     * Isian temuan pertama, seperti yang terkirim dari halaman isian: seluruh
     * panel ikut, bernama tem[id temuan][…]. `$ubah` menimpa sebagian isian
     * tindak lanjut pertamanya.
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
            /* Isian wajib bertanda bintang, dijelaskan sekali di atas — tanpa
               tulisan "tidak wajib". */
            ->assertSee('wajib diisi')->assertDontSee('tidak wajib')
            /* Tombol Tambah di ujung tiap deret tab. */
            ->assertSee('Tambah temuan')->assertSee('Tambah rekomendasi')->assertSee('Tambah tindak lanjut')
            ->assertSee('role="tablist"', false)
            ->getContent();

        /* Enter di isian satu baris memakai tombol kirim pertama: yang itu
           cuma menyimpan, bukan "Kembali ke beranda". */
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

        /* "Tunjukkan" membuka temuan, rekomendasi, dan tab tindak lanjutnya,
           lalu alamatnya menunjuk panel itu dan kursornya ditaruh di sana. */
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

        /* Isiannya utuh. Draf yang dilanjutkan dibuka di halaman isian, dengan
           bagian surat mengikuti kelengkapannya — seperti di prototipe. */
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

    /**
     * Formulir kosong bukan draf (28 Sep, laporan Hizkia): "Kembali ke beranda"
     * dari formulir kosong dulu menyimpan draf kosong — tombol batang atas jadi
     * "Lanjutkan draf laporan" dan formulirnya menyebut "Melanjutkan draf",
     * tanpa isi dan tanpa tombol Kosongkan.
     */
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

        /* Sisa draf kosong dari sebelum perbaikan tidak dianggap draf, dan
           dibuang begitu formulirnya dibuka. */
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

    /* ================================================================
       KIRIMAN DARI SKRIP (29 Sep): ditukar di tempat, tanpa memuat ulang
       ================================================================ */

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

    /**
     * Formulir yang baru dibuka, belum ada draf: isian temuan pada kiriman
     * pertama tidak terbuang lagi. Dulu pengenal temuannya acak di tiap
     * permintaan, jadi satuan kerja yang pertama dicentang hilang lagi.
     */
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
}
