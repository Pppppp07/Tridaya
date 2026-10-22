<?php

namespace Tests\Feature;

use App\Aksi\UrusanSiptl;
use App\Enums\PeranPengguna;
use App\Enums\StatusTindakLanjut;
use App\Enums\SumberLaporan;
use App\Models\DrafHistoris;
use App\Models\KeputusanVerifikasi;
use App\Models\Laporan;
use App\Models\Referensi;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Models\Satker;
use App\Models\User;
use App\Models\Verifikasi;
use App\Support\Historis\BacaExcel;
use App\Support\Historis\PetaKolom;
use App\Support\Historis\ReviewHistoris;
use App\Support\RiwayatTindakLanjut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Phar;
use PharData;
use Tests\PakaiDataContoh;
use Tests\TestCase;

class ReviewHistorisTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        $this->masuk('setba@contoh.test');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $p) {
            if (is_file($p)) {
                unlink($p);
            }
        }
        parent::tearDown();
    }

    /** Lembar sederhana: kepala di baris 4, data mulai baris 5, semua sel berupa teks. */
    private function lembar(array $kepala, array ...$baris): string
    {
        $sel = fn ($r, $i, $v) => '<c r="'.chr(65 + $i).$r.'" t="inlineStr"><is><t>'.htmlspecialchars($v, ENT_XML1).'</t></is></c>';
        $tulis = fn ($r, $isi) => '<row r="'.$r.'">'.implode('', array_map(fn ($i, $v) => $sel($r, $i, $v), array_keys($isi), $isi)).'</row>';
        $xml = $tulis(4, $kepala);
        foreach ($baris as $i => $isi) {
            $xml .= $tulis(5 + $i, $isi);
        }

        return '<worksheet><sheetData>'.$xml.'</sheetData></worksheet>';
    }

    private function csv(string $isi): UploadedFile
    {
        $p = tempnam(sys_get_temp_dir(), 'hist-');
        $this->files[] = $p;
        file_put_contents($p, $isi);

        return new UploadedFile($p, 'historis.csv', 'text/csv', null, true);
    }

    private function excel(?string $xml = null, ?string $disimpan = null): UploadedFile
    {
        $p = tempnam(sys_get_temp_dir(), 'hist-');
        $this->files[] = $p;
        $path = $p.'.zip';
        $this->files[] = $path;
        $zip = new PharData($path, 0, null, Phar::ZIP);
        $zip['xl/workbook.xml'] = '<workbook xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="PTL" r:id="r1"/></sheets></workbook>';
        $zip['xl/_rels/workbook.xml.rels'] = '<Relationships><Relationship Id="r1" Target="worksheets/sheet1.xml"/></Relationships>';
        if ($disimpan) {
            $zip['docProps/core.xml'] = '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dcterms="http://purl.org/dc/terms/"><dcterms:modified>'.$disimpan.'</dcterms:modified></cp:coreProperties>';
        }
        $zip['xl/worksheets/sheet1.xml'] = $xml ?? '<worksheet><sheetData><row r="4"><c r="A4" t="inlineStr"><is><t>No LHP</t></is></c><c r="B4" t="inlineStr"><is><t>Reff IDT</t></is></c><c r="C4" t="inlineStr"><is><t>Rekomendasi Temuan Besar</t></is></c></row><row r="5"><c r="A5" t="inlineStr"><is><t>HIST/2019</t></is></c><c r="B5" t="str"><f>CONCAT()</f><v>2019.HIST.1.a</v></c><c r="C5" t="inlineStr"><is><t>Perbaiki dokumen lama</t></is></c></row><row r="6"><c r="Z6"><f>COUNTIF()</f><v>1</v></c></row></sheetData></worksheet>';
        unset($zip);

        return new UploadedFile($path, 'historis.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function draft(array $ubah = [], array $tambahan = []): DrafHistoris
    {
        $base = (new BacaExcel)->baca($this->excel()->getRealPath());
        $d = $base['baris'][0]['data'];
        $d = array_replace($d, [
            'nomor' => 'HIST/2019', 'tgl_surat' => '2019-01-02', 'tgl_terima' => '2019-01-05',
            'ref_lhp' => '1.a', 'nomor_temuan' => '1', 'judul' => 'Temuan historis',
            'satker_id' => (string) Satker::where('aktif', true)->first()->id,
            'sifat_id' => (string) Referensi::where('jenis', 'sifat_rekom')->where('aktif', true)->first()->id,
            'bentuk_id' => (string) Referensi::where('jenis', 'bentuk_tl')->where('aktif', true)->first()->id,
            'nilai' => '100', 'nilai_itjen' => '100', 'nilai_bpk' => '100', 'hasil' => 'M',
            'status_bpk' => 'SS', 'posisi' => 'tuntas', 'tanggal_acuan' => '2020-01-01',
        ], $ubah);
        $base['baris'][0]['data'] = $d;
        $base['baris'][0]['diperiksa'] = true;
        foreach ($tambahan as $i => $x) {
            $base['baris'][] = array_replace($base['baris'][0], ['baris' => 7 + $i, 'data' => array_replace($d, $x)]);
        }

        return DrafHistoris::create(['user_id' => auth()->id(), 'nama_berkas' => 'historis.xlsx', 'berkas' => 'hist.xlsx', 'sha256' => str_repeat('a', 64), 'isian' => $base]);
    }

    private function konfirmasi(DrafHistoris $draf)
    {
        return $this->post(route('laporan.historis.impor', $draf), ['versi' => $draf->versi, 'konfirmasi' => '1']);
    }

    public function test_upload_tidak_menulis_laporan_dan_mengabaikan_baris_formula_bantu(): void
    {
        $awal = Laporan::count();
        $f = $this->excel();
        $this->post(route('laporan.historis.upload'), ['excel' => $f])->assertRedirect();
        $d = DrafHistoris::firstOrFail();
        $this->assertCount(1, $d->isian['baris']);
        $this->assertSame('2019.HIST.1.a', $d->isian['baris'][0]['data']['kode']);
        $this->assertSame($awal, Laporan::count());
        $this->get(route('laporan.historis.review', $d))->assertOk()->assertSee('Perlu review')->assertSee('Konfirmasi hasil review');
        $this->get(route('laporan.historis.baris', [$d, 0]))->assertOk()->assertSee('Isi file asli');
        $this->get(route('laporan.historis.sumber', $d))->assertOk()->assertDownload();
        $this->post(route('laporan.historis.upload'), ['excel' => $f])->assertRedirect(route('laporan.historis.review', $d));
        $this->assertSame(1, DrafHistoris::count());
    }

    public function test_draft_pribadi_dan_hanya_peran_yang_diizinkan(): void
    {
        $d = $this->draft();
        $this->actingAs(User::factory()->create(['peran' => 'setba', 'aktif' => true]));
        foreach (['review', 'sumber'] as $r) {
            $this->get(route('laporan.historis.'.$r, $d))->assertNotFound();
        }
        $this->konfirmasi($d)->assertNotFound();
        $this->masuk('uki@contoh.test')->get(route('laporan.historis.index'))->assertForbidden();
        $this->post(route('laporan.historis.upload'), ['excel' => $this->excel()])->assertForbidden();
    }

    public function test_impor_status_selesai_tanpa_mengarang_tanggal_siptl_dan_klik_ulang_aman(): void
    {
        $d = $this->draft();
        $jumlah = Rekomendasi::count();
        $this->konfirmasi($d)->assertSessionHasNoErrors()->assertRedirect(route('laporan.historis.review', $d));
        $s = Sasaran::where('draf_historis_id', $d->id)->firstOrFail();
        $this->assertNull($s->siptl_tanggal);
        $this->assertSame(StatusTindakLanjut::SS, $s->statusSiptl());
        $this->assertFalse($s->perluUnggah(SumberLaporan::LHP));
        $this->assertSame(0, $s->sisaNilaiBpk());
        $this->assertSame(100, $s->nilaiDiakuiItjen());
        $this->assertTrue($s->presentasi(SumberLaporan::LHP)['selesai']);
        $this->konfirmasi($d)->assertSessionHasNoErrors();
        $this->assertSame($jumlah + 1, Rekomendasi::count());
        $this->get(route('laporan.historis.review', $d))->assertOk()->assertSee('Data sudah masuk');
        $this->get(route('rekomendasi.show', $s->rekomendasi()))->assertOk()->assertSee('Historis');
        $kartu = RiwayatTindakLanjut::kartu($s->rekomendasi(), PeranPengguna::SETBA, null);
        $this->assertSame('HISTORIS', $kartu[0]['baris'][0]['sumber']);
        $this->post(route('siptl.unggah', $s), ['tanggal' => '2020-01-01'])->assertStatus(422);
    }

    public function test_nilai_sebagian_berubah_saat_ada_penilaian_bpk_baru(): void
    {
        $d = $this->draft(['status_bpk' => 'BT', 'nilai_bpk' => '25']);
        $this->konfirmasi($d)->assertSessionHasNoErrors();
        $s = Sasaran::where('draf_historis_id', $d->id)->firstOrFail();
        $this->assertSame(75, $s->sisaNilaiBpk());
        UrusanSiptl::status($s->rekomendasi(), $s, StatusTindakLanjut::SS, null, '2020-01-02');
        $this->assertSame(0, $s->refresh()->sisaNilaiBpk());
    }

    public function test_rekomendasi_yang_sama_digabung_dan_bukan_dua_rekomendasi(): void
    {
        $s2 = (string) Satker::where('aktif', true)->skip(1)->first()->id;
        $d = $this->draft([], [['satker_id' => $s2, 'nilai' => '50', 'nilai_itjen' => '50', 'nilai_bpk' => '50']]);
        $this->konfirmasi($d)->assertSessionHasNoErrors();
        $r = Rekomendasi::where('kode', '2019.HIST.1.a')->firstOrFail();
        $this->assertSame(2, $r->sasaran()->count());
        $this->assertSame(150, (int) $r->nilai_pulih);
        $this->assertCount(2, $d->refresh()->isian['hasil']);
    }

    public function test_duplikat_dan_data_belum_diperiksa_membatalkan_seluruh_impor(): void
    {
        $existing = Rekomendasi::first();
        $d = $this->draft([], [['kode' => $existing->kode, 'ref_lhp' => '2.a']]);
        $awal = Laporan::count();
        $this->konfirmasi($d)->assertSessionHasErrors('review');
        $this->assertSame($awal, Laporan::count());
        $this->assertDatabaseMissing('rekomendasis', ['kode' => '2019.HIST.1.a']);
        $this->assertNull($d->refresh()->diimpor_pada);
        $isian = $d->isian;
        $isian['baris'][1]['ikut'] = false;
        $isian['baris'][0]['diperiksa'] = false;
        $d->update(['isian' => $isian]);
        $this->konfirmasi($d)->assertSessionHasErrors('review');
        $this->assertDatabaseMissing('rekomendasis', ['kode' => '2019.HIST.1.a']);
    }

    public function test_koreksi_tersimpan_tanpa_mengubah_sumber_dan_versi_lama_ditolak(): void
    {
        $d = $this->draft();
        $asli = $d->isian['baris'][0]['asli'];
        $data = array_replace($d->isian['baris'][0]['data'], ['judul' => 'Judul dikoreksi']);
        $url = route('laporan.historis.simpan', [$d, 0]);
        $this->post($url, ['versi' => 1, 'data' => $data, 'ikut' => 1, 'diperiksa' => 1])->assertSessionHasNoErrors();
        $this->post($url, ['versi' => 1, 'data' => $data, 'ikut' => 1, 'diperiksa' => 1])->assertSessionHasErrors('review');
        $this->konfirmasi($d)->assertSessionHasErrors('review');
        $d->refresh();
        $this->assertSame($asli, $d->isian['baris'][0]['asli']);
        $this->assertSame('Judul dikoreksi', $d->isian['baris'][0]['data']['judul']);
        $this->konfirmasi($d)->assertSessionHasNoErrors();
        $this->post($url, ['versi' => $d->refresh()->versi, 'data' => $data, 'ikut' => 1, 'diperiksa' => 1])->assertStatus(409);
    }

    public function test_tanggal_massal_melepas_tanda_sudah_diperiksa(): void
    {
        $d = $this->draft();
        $this->post(route('laporan.historis.massal', $d), ['versi' => 1, 'nomor' => 'HIST/2019', 'tanggal_acuan' => '2021-01-01'])->assertSessionHasNoErrors();
        $this->assertFalse($d->refresh()->isian['baris'][0]['diperiksa']);
        $this->assertSame('2021-01-01', $d->isian['baris'][0]['data']['tanggal_acuan']);
    }

    public function test_xxe_dan_file_palsu_ditolak_sebelum_draft_dibuat(): void
    {
        $this->post(route('laporan.historis.upload'), ['excel' => $this->excel('<!DOCTYPE x [<!ENTITY evil SYSTEM "file:///etc/passwd">]><worksheet/>')])->assertSessionHasErrors('excel');
        $this->post(route('laporan.historis.upload'), ['excel' => UploadedFile::fake()->createWithContent('palsu.xlsx', '<script>alert(1)</script>')])->assertSessionHasErrors('excel');
        $this->assertSame(0, DrafHistoris::count());
    }

    public function test_nilai_tanggal_dan_pilihan_master_tidak_valid_menghalangi_impor(): void
    {
        $d = $this->draft(['nilai' => '-1', 'tgl_surat' => '2019-02-30', 'satker_id' => '999999', 'tanggal_acuan' => '2100-01-01']);
        $this->konfirmasi($d)->assertSessionHasErrors('review');
        $this->assertDatabaseMissing('rekomendasis', ['kode' => '2019.HIST.1.a']);
    }

    public function test_kode_satker_kosong_tidak_memilih_satker_pertama(): void
    {
        Satker::first()->update(['kode_satker' => null]);
        $data = (new BacaExcel)->baca($this->excel()->getRealPath());
        $isian = app(ReviewHistoris::class)->awal($data);
        $this->assertSame('', $isian['baris'][0]['data']['satker_id']);
    }

    public function test_arsip_yang_mengembang_terlalu_besar_ditolak(): void
    {
        $file = $this->excel();
        $zip = new PharData($file->getRealPath());
        $zip['xl/sharedStrings.xml'] = str_repeat('x', 4 * 1024 * 1024 + 1);
        $zip->compressFiles(Phar::GZ);
        unset($zip);
        $this->post(route('laporan.historis.upload'), ['excel' => $file])->assertSessionHasErrors('excel');
        $this->assertSame(0, DrafHistoris::count());
    }

    /** Format workbook pemantauan Setba: nomor dan tanggal LHP satu sel, tanggal teks, sen, posisi. */
    public function test_format_excel_pemantauan_setba_dipetakan_utuh(): void
    {
        $xml = $this->lembar(
            ['No LHP', 'Reff LHP', 'Reff IDT', 'Judul Temuan Besar', 'Rekomendasi Temuan Besar', 'Kode Satker',
                'Nilai Rekomendasi (IDR)', 'Nilai SS (IDR)', 'Nilai Memadai (IDR)', 'Status SiPTL', 'Status Verifikasi',
                'Status/Posisi Dokumen TL', 'Due Date Renaksi', 'Rencana Aksi'],
            ['11/XII/09/2005 5 September 2005', '58.B.8.b.', '2005.11.58.B.8.b', 'Temuan lama', 'Perbaiki dokumen', '694431',
                '87295888.19', '87295888.19', '87295888.19', 'BT', 'Memadai', 'Diproses SiPTL',
                '29 Juli 2021 (Konfirmasi ke Satker)', 'Kirim bukti'],
        );
        $d = (new BacaExcel)->baca($this->excel($xml, '2026-08-12T03:00:00Z')->getRealPath())['baris'][0]['data'];
        $this->assertSame(['11/XII/09/2005', '2005-09-05', '2005-09-05'], [$d['nomor'], $d['tgl_surat'], $d['tgl_terima']]);
        $this->assertSame('58.B.8', $d['nomor_temuan']);
        $this->assertSame(['87295888', '87295888', '87295888'], [$d['nilai'], $d['nilai_bpk'], $d['nilai_itjen']]);
        $this->assertSame(['M', 'tuntas'], [$d['hasil'], $d['posisi']]);
        $this->assertSame('2021-07-29', $d['renaksi']);
        $this->assertStringContainsString('Tenggat rencana aksi: Konfirmasi ke Satker', $d['rencana_aksi']);
        /* Tanggal acuan bawaan = tanggal file terakhir disimpan. */
        $this->assertSame('2026-08-12', $d['tanggal_acuan']);

        $this->assertSame(['28/LHP/XVII/04/2008', ''], BacaExcel::pisahNomorLhp('28/LHP/XVII/04/2008'));
        $this->assertSame(['12.b/LHP/XVII/05/2025', '2025-05-19'], BacaExcel::pisahNomorLhp('12.b/LHP/XVII/05/2025 tanggal 19 Mei 2025'));
        $this->assertSame(['5b/LHP/XVII/05/2022', '2022-05-27'], BacaExcel::pisahNomorLhp('5b/LHP/XVII/05/2022 Tanggal 27 Mei 2022'));
        foreach (['Selesai' => 'tuntas', 'Diproses SiPTL' => 'tuntas', 'Dirposes Itjen' => 'inspektorat', 'Diproses UKI' => 'uki',
            'Diproses Satker/ SetUnor' => 'satker', 'Entah' => ''] as $teks => $posisi) {
            $this->assertSame($posisi, BacaExcel::posisi($teks), $teks);
        }
    }

    public function test_baris_isi_sama_persis_digabung_yang_berbeda_tetap(): void
    {
        $kepala = ['No', 'No LHP', 'Reff IDT', 'Rekomendasi Temuan Besar', 'Rencana Aksi', 'Flag Reff IDT Unik'];
        $xml = $this->lembar($kepala,
            ['1', 'HIST/2019', '2019.H.1.a', 'Rekomendasi', 'Sama', '1'],
            ['2', 'HIST/2019', '2019.H.1.a', 'Rekomendasi', 'Sama', '0'],
            ['3', 'HIST/2019', '2019.H.1.a', 'Rekomendasi', 'Beda', '0'],
        );
        $review = app(ReviewHistoris::class);
        $isian = $review->awal((new BacaExcel)->baca($this->excel($xml)->getRealPath()));
        $this->assertCount(2, $isian['baris']);
        $this->assertSame([6], $isian['baris'][0]['kembar']);
        $this->assertSame([], $isian['baris'][1]['kembar']);
        $this->assertStringContainsString('Juga mewakili baris 6', implode(' ', $review->periksa($isian)[0]['warnings']));
    }

    public function test_nomor_temuan_dari_awalan_ref_lhp_satu_judul(): void
    {
        $kepala = ['No LHP', 'Reff LHP', 'Reff IDT', 'Judul Temuan Besar', 'Rekomendasi Temuan Besar'];
        $xml = $this->lembar($kepala,
            ['7b/LHP/XVII/05/2021 Tanggal 24 Mei 2021', 'A.2.10.a', '2021.7b.A.2.10.a', 'Sewa asrama', 'Rekomendasi a'],
            ['7b/LHP/XVII/05/2021 Tanggal 24 Mei 2021', 'A.2.10.b.1', '2021.7b.A.2.10.b.1', 'Sewa asrama', 'Rekomendasi b.1'],
            ['7b/LHP/XVII/05/2021 Tanggal 24 Mei 2021', 'A.3.1.c', '2021.7b.A.3.1.c', 'Temuan lain', 'Rekomendasi c'],
        );
        $isian = app(ReviewHistoris::class)->awal((new BacaExcel)->baca($this->excel($xml)->getRealPath()));
        $this->assertSame(['A.2.10', 'A.2.10', 'A.3.1'], array_map(fn ($r) => $r['data']['nomor_temuan'], $isian['baris']));
        $this->assertSame('7b/LHP/XVII/05/2021', $isian['baris'][0]['data']['nomor']);
    }

    public function test_satker_sama_dengan_isi_berbeda_jadi_tindak_lanjut_terpisah(): void
    {
        $d = $this->draft([], [['nilai' => '50', 'nilai_itjen' => '50', 'nilai_bpk' => '50']]);
        $rows = app(ReviewHistoris::class)->periksa($d->isian);
        $this->assertSame([], $rows[1]['errors']);
        $this->assertStringContainsString('tindak lanjut terpisah', implode(' ', $rows[1]['warnings']));
        $this->konfirmasi($d)->assertSessionHasNoErrors();
        $r = Rekomendasi::where('kode', '2019.HIST.1.a')->firstOrFail();
        $this->assertSame(2, $r->sasaran()->count());
        $this->assertSame(2, $r->tindakan()->count());
        $this->assertCount(1, $r->sasaran()->pluck('sasarans.satker_id')->unique());
    }

    public function test_tandai_sekaligus_hanya_baris_tanpa_masalah(): void
    {
        $d = $this->draft([], [['kode' => '2019.HIST.2.a', 'nilai' => 'x']]);
        $isian = $d->isian;
        $isian['baris'][0]['diperiksa'] = false;
        $isian['baris'][1]['diperiksa'] = false;
        $d->update(['isian' => $isian]);
        $this->get(route('laporan.historis.review', $d))->assertOk()->assertSee('Tandai 1 baris tanpa masalah sudah diperiksa');
        $this->post(route('laporan.historis.periksa', $d), ['versi' => $d->versi])->assertRedirect(route('laporan.historis.review', $d));
        $d->refresh();
        $this->assertTrue($d->isian['baris'][0]['diperiksa']);
        $this->assertFalse($d->isian['baris'][1]['diperiksa']);
        $this->post(route('laporan.historis.periksa', $d), ['versi' => 1])->assertSessionHasErrors('review');
    }

    public function test_csv_titik_koma_dengan_angka_dan_tanggal_indonesia_terbaca(): void
    {
        $isi = "\xEF\xBB\xBF".'Pemantauan TL Rekomendasi BPK;;;;;;;'."\n"
            .'No LHP;Reff LHP;Reff IDT;Rekomendasi Temuan Besar;Kode Satker;Nilai Rekomendasi (IDR);Due Date Renaksi;Status/Posisi Dokumen TL'."\n"
            .'"11/XII/09/2005 5 September 2005";1.7;2005.11.1.7;"Perbaiki; dokumen";694431;87.295.888,19;29/07/2021;Diproses UKI'."\n";
        $this->post(route('laporan.historis.upload'), ['excel' => $this->csv($isi)])->assertRedirect();
        $d = DrafHistoris::latest('id')->firstOrFail();
        $row = $d->isian['baris'][0];
        $this->assertSame(['CSV', 3], [$row['sheet'], $row['baris']]);
        $this->assertSame(['11/XII/09/2005', '2005-09-05', '1', '87295888', '2021-07-29', 'uki', 'Perbaiki; dokumen'],
            [$row['data']['nomor'], $row['data']['tgl_surat'], $row['data']['nomor_temuan'], $row['data']['nilai'],
                $row['data']['renaksi'], $row['data']['posisi'], $row['data']['uraian']]);
        $this->get(route('laporan.historis.sumber', $d))->assertOk()->assertDownload('sumber-historis-'.$d->id.'.csv');
    }

    public function test_perbandingan_menandai_keadaan_tiap_kolom(): void
    {
        $xml = $this->lembar(
            ['No LHP', 'Reff IDT', 'Rekomendasi Temuan Besar', 'Bentuk Tindak Lanjut', 'Kode Satker', 'Due Date Renaksi', 'Nilai Rekomendasi (IDR)'],
            ['HIST/2019 2 Januari 2019', '2019.H.1.a', 'Rekomendasi', 'teks bebas di luar daftar', '999999', '', 'Rp sekian'],
        );
        $review = app(ReviewHistoris::class);
        $isian = $review->awal((new BacaExcel)->baca($this->excel($xml)->getRealPath()));
        $rows = $review->periksa($isian);
        $b = PetaKolom::bandingkan($rows[0], $review->pilihan());
        $keadaan = array_map(fn ($x) => $x['keadaan'], $b);
        $this->assertSame(['terbaca', 'terbaca', 'aturan', 'aturan'],
            [$keadaan['nomor'], $keadaan['tgl_surat'], $keadaan['tgl_terima'], $keadaan['sumber']]);
        /* Ada isinya di file tetapi tidak bisa dipakai sistem. */
        $this->assertSame(['tidak_terbaca', 'tidak_terbaca', 'tidak_terbaca'], [$keadaan['bentuk_id'], $keadaan['satker_id'], $keadaan['nilai']]);
        $this->assertSame('Tidak sama dengan daftar Bentuk tindak lanjut di Data Master; teksnya disimpan apa adanya.', $b['bentuk_id']['alasan']);
        /* Teks aslinya tetap tersimpan dan bisa diimpor, tetapi bukan baris perbandingan tersendiri. */
        $this->assertSame('teks bebas di luar daftar', $rows[0]['data']['bentuk_teks']);
        $this->assertArrayNotHasKey('bentuk_teks', $b);
        $this->assertSame([], $rows[0]['errors_kolom']['bentuk_id'] ?? []);
        $this->assertSame([['judul' => 'Bentuk Tindak Lanjut', 'isi' => 'teks bebas di luar daftar']], $b['bentuk_id']['asal']);
        /* Kolomnya ada tetapi kosong, dan kolom yang tidak ada di file sama sekali. */
        $this->assertSame(['kosong', 'tidak_ada', 'tidak_ada'], [$keadaan['renaksi'], $keadaan['status_bpk'], $keadaan['tanggal_acuan']]);
        $this->assertArrayHasKey('bentuk_id', PetaKolom::masalah($b));
        $this->assertArrayNotHasKey('renaksi', PetaKolom::masalah($b));
        $ringkas = PetaKolom::ringkas([$b], $rows);
        $this->assertSame(1, $ringkas['kolom']['bentuk_id']['jumlah']['tidak_terbaca']);
        $this->assertSame(['Bentuk Tindak Lanjut'], $ringkas['kolom']['bentuk_id']['dari']);

        $isian['baris'][0]['data']['kode'] = '2019.H.1.b';
        $this->assertSame('diubah', PetaKolom::bandingkan($isian['baris'][0], $review->pilihan())['kode']['keadaan']);
    }

    public function test_halaman_review_menampilkan_ringkasan_kolom_dan_perbandingan(): void
    {
        $xml = $this->lembar(['No LHP', 'Reff IDT', 'Rekomendasi Temuan Besar', 'Bentuk Tindak Lanjut', 'Pejabat Terkait', 'Unit Organisasi'],
            ['HIST/2019 2 Januari 2019', '2019.H.1.a', 'Rekomendasi', 'teks bebas', 'Nama Pejabat', 'BPSDM']);
        $this->post(route('laporan.historis.upload'), ['excel' => $this->excel($xml)])->assertRedirect();
        $d = DrafHistoris::latest('id')->firstOrFail();
        $this->get(route('laporan.historis.review', $d))->assertOk()
            ->assertSee('Kolom yang dibaca sistem')
            ->assertSee('Kolom file yang tidak disimpan (1)')
            ->assertSee('Selalu BPSDM.')
            ->assertDontSee('Nama Pejabat')
            ->assertDontSee('Teks bentuk dari file')
            ->assertSee('Tidak terbaca:')
            ->assertSee('data-href="'.route('laporan.historis.baris', [$d, 0]).'"', false);
        $this->get(route('laporan.historis.review', [$d, 'kolom' => 'bentuk_id', 'keadaan' => 'tidak_terbaca']))->assertOk()
            ->assertSee('Menampilkan 1 baris dengan')->assertSee('2019.H.1.a');
        $this->get(route('laporan.historis.review', [$d, 'kolom' => 'bentuk_id', 'keadaan' => 'terbaca']))->assertOk()
            ->assertSee('Tidak ada baris pada filter ini.');
        $this->get(route('laporan.historis.baris', [$d, 0]))->assertOk()
            ->assertSee('Perbandingan data')->assertSee('Diisi sistem')
            ->assertSee('teks bebas')->assertSee('Tidak sama dengan daftar Bentuk tindak lanjut di Data Master; teksnya disimpan apa adanya.')
            ->assertSee('Kolomnya tidak ada di file')
            ->assertSee('Kolom file lain yang tidak disimpan (1)')
            ->assertDontSee('Nama Pejabat')
            /* Teks dari file berada di bawah pilihan daftar pada baris Bentuk tindak lanjut. */
            ->assertSeeInOrder(['id="kolom-bentuk_id"', 'name="data[bentuk_id]"', 'Teks bentuk dari file', 'name="data[bentuk_teks]" value="teks bebas"'], false)
            ->assertDontSee('id="kolom-bentuk_teks"', false);
        /* Disimpan lewat formulir yang sama, termasuk teks bentuknya. */
        $data = array_replace($d->refresh()->isian['baris'][0]['data'], ['bentuk_teks' => 'teks bebas diperbaiki']);
        $this->post(route('laporan.historis.simpan', [$d, 0]), ['versi' => $d->versi, 'data' => $data, 'ikut' => 1, 'diperiksa' => 0])
            ->assertSessionHasNoErrors();
        $this->assertSame('teks bebas diperbaiki', $d->refresh()->isian['baris'][0]['data']['bentuk_teks']);
    }

    public function test_filter_review_bisa_ditukar_di_tempat(): void
    {
        $d = $this->draft([], [['kode' => '2019.HIST.2.a', 'nilai' => 'x']]);
        $this->get(route('laporan.historis.review', $d))->assertOk()
            ->assertSee('id="daftar-baris" data-ganti-di-tempat', false)
            ->assertSee('href="'.route('laporan.historis.review', [$d, 'filter' => 'perlu']).'" data-di-tempat', false)
            ->assertSee('href="'.route('laporan.historis.review', $d).'" data-di-tempat', false);
        /* Permintaan dari skrip mendapat isi halaman saja, dengan penanda yang sama. */
        $this->get(route('laporan.historis.review', [$d, 'filter' => 'perlu']), ['X-Requested-With' => 'XMLHttpRequest', 'X-Simtlhp-Fragment' => 'isi'])
            ->assertOk()->assertDontSee('<html', false)->assertSee('class="body hrv"', false)
            ->assertSee('data-ganti-di-tempat', false)->assertSee('2019.HIST.2.a')->assertDontSee('2019.HIST.1.a');
    }

    public function test_sumber_laporan_diambil_dari_nomor_laporan(): void
    {
        $xml = $this->lembar(['No LHP', 'Reff IDT', 'Rekomendasi Temuan Besar'],
            ['12.b/LHP/XVII/05/2025 tanggal 19 Mei 2025', '2025.12.b.1.a', 'Rekomendasi a'],
            ['07/LHA/ITJEN/04/2026 5 Mei 2026', '2026.07.1.a', 'Rekomendasi b'],
            ['11/XII/09/2005 5 September 2005', '2005.11.1.7', 'Rekomendasi c']);
        $review = app(ReviewHistoris::class);
        $isian = $review->awal((new BacaExcel)->baca($this->excel($xml)->getRealPath()));
        $this->assertSame(['LHP', 'LHA', 'LHP'], array_map(fn ($r) => $r['data']['sumber'], $isian['baris']));
        $rows = $review->periksa($isian);
        $b = PetaKolom::bandingkan($rows[1], $review->pilihan());
        $this->assertSame('aturan', $b['sumber']['keadaan']);
        $this->assertSame([['judul' => 'No LHP', 'isi' => '07/LHA/ITJEN/04/2026 5 Mei 2026']], $b['sumber']['asal']);
        $this->assertStringNotContainsString('Sumber laporan:', implode(' ', $rows[0]['warnings']));
        $this->assertStringContainsString('Sumber laporan: Nomor laporan tidak memuat LHP atau LHA', implode(' ', $rows[2]['warnings']));
    }

    public function test_tanggal_bersama_bisa_untuk_semua_laporan(): void
    {
        $d = $this->draft([], [['kode' => '2019.HIST.2.a', 'nomor' => 'HIST/2020']]);
        $this->post(route('laporan.historis.massal', $d), ['versi' => 1, 'nomor' => '*', 'tanggal_acuan' => '2021-01-01'])->assertSessionHasNoErrors();
        $this->assertSame(['2021-01-01', '2021-01-01'], array_map(fn ($r) => $r['data']['tanggal_acuan'], $d->refresh()->isian['baris']));
    }

    public function test_sumber_tptd_tidak_diam_diam_mengubah_sisa_nilai(): void
    {
        $d = $this->draft();
        $isian = $d->isian;
        $isian['baris'][0]['asli']['Nilai TPTD (IDR)'] = '50';
        $d->update(['isian' => $isian]);
        $this->konfirmasi($d)->assertSessionHasErrors('review');
        $this->assertDatabaseMissing('rekomendasis', ['kode' => '2019.HIST.1.a']);
    }

    public function test_isian_draft_tersimpan_terkompresi_dan_draft_lama_tetap_terbaca(): void
    {
        $this->post(route('laporan.historis.upload'), ['excel' => $this->excel()])->assertRedirect();
        $d = DrafHistoris::firstOrFail();
        $mentah = json_decode(DB::table('draf_historis')->where('id', $d->id)->value('isian'), true);
        $this->assertSame(1, $mentah['jumlah']);
        $this->assertArrayHasKey('gz', $mentah);
        $this->assertSame('2019.HIST.1.a', $d->isian['baris'][0]['data']['kode']);
        $this->get(route('laporan.historis.index'))->assertOk()->assertSee('<td class="num">1</td>', false);
        /* Draft yang tersimpan sebelum pemampatan masih JSON biasa. */
        DB::table('draf_historis')->where('id', $d->id)->update(['isian' => json_encode($d->isian)]);
        $this->assertSame('2019.HIST.1.a', $d->refresh()->isian['baris'][0]['data']['kode']);
        $this->get(route('laporan.historis.index'))->assertOk()->assertSee('<td class="num">1</td>', false);
    }

    public function test_kolom_yang_tidak_dipakai_dibuang_saat_file_dibaca(): void
    {
        $buang = BacaExcel::KOLOM_TIDAK_DIPAKAI;
        $xml = $this->lembar(array_merge(['No LHP', 'Reff IDT', 'Rekomendasi Temuan Besar', 'Status Verifikasi'], $buang),
            array_merge(['HIST/2019 2 Januari 2019', '2019.H.1.a', 'Rekomendasi', 'Belum Memadai'], array_fill(0, count($buang), 'isi')));
        $row = (new BacaExcel)->baca($this->excel($xml)->getRealPath())['baris'][0];
        $this->assertSame(['No LHP', 'Reff IDT', 'Rekomendasi Temuan Besar', 'Status Verifikasi'], array_keys($row['asli']));
        /* Hasil Inspektorat dibaca dari Status Verifikasi, bukan Status Verifikasi Olah Unor. */
        $this->assertSame('BM', $row['data']['hasil']);
    }

    public function test_bentuk_di_luar_daftar_diimpor_dengan_teks_aslinya_beserta_paket(): void
    {
        $judul = rtrim(str_repeat('Judul temuan panjang ', 13));
        $d = $this->draft(['bentuk_id' => '', 'bentuk_teks' => 'sertifikat', 'paket' => 'Tata kelola BMN',
            'klasifikasi_renaksi' => 'Pendek', 'renaksi' => '2019-03-01', 'judul' => $judul]);
        $rows = app(ReviewHistoris::class)->periksa($d->isian);
        $this->assertSame([], $rows[0]['errors']);
        $this->assertStringContainsString('"sertifikat" tidak ada di daftar Data Master', implode(' ', $rows[0]['warnings']));
        $this->konfirmasi($d)->assertSessionHasNoErrors();
        $s = Sasaran::where('draf_historis_id', $d->id)->firstOrFail();
        $tk = $s->tindakan;
        $this->assertSame([null, 'sertifikat', 'Tata kelola BMN', 'Pendek', 'sertifikat'],
            [$tk->bentuk_id, $tk->bentuk_teks, $tk->keterangan_paket, $tk->klasifikasi_renaksi, $tk->namaBentuk()]);
        $this->assertSame($judul, $s->rekomendasi()->temuan->judul);
        $this->get(route('rekomendasi.show', $s->rekomendasi()))->assertOk()
            ->assertSee('teks data lama')->assertSee('sertifikat')->assertSee('Tata kelola BMN')->assertSee('Rencana aksi 01');
    }

    public function test_chv_lhv_uraian_dan_catatan_bpk_ikut_diimpor(): void
    {
        $d = $this->draft(['chv' => '7/7/7/77/2025/701 7/7/7/77/2026/702', 'tgl_lhv' => '10 November 2025 19 Februari 2026',
            'uraian_verifikasi' => 'Surat teguran sudah disampaikan.', 'catatan_bpk' => 'Hasil Telaah sesuai rekomendasi.'],
            [['kode' => '2019.HIST.2.a', 'ref_lhp' => '2.a', 'chv' => '7/7/7/77/2024/703', 'tgl_lhv' => '', 'uraian_verifikasi' => '']]);
        $rows = app(ReviewHistoris::class)->periksa($d->isian);
        $this->assertStringContainsString('hanya dicatat di uraian verifikasi', implode(' ', $rows[1]['warnings']));
        $this->konfirmasi($d)->assertSessionHasNoErrors();

        $s = Sasaran::where('draf_historis_id', $d->id)->orderBy('id')->get();
        $this->assertSame(['Hasil Telaah sesuai rekomendasi.', 'Surat teguran sudah disampaikan.'], [$s[0]->catatan_bpk, $s[0]->uraian_verifikasi]);
        /* CHV terakhir jadi surat verifikasi; yang sebelumnya disebut di catatannya. */
        $v = Verifikasi::where('jenis', Verifikasi::CHV)->where('nomor_surat', '7/7/7/77/2026/702')->firstOrFail();
        $this->assertSame(['2026-02-19', '2026-02-19'], [$v->tgl_surat->toDateString(), $v->tgl_lhv->toDateString()]);
        $this->assertSame(['Inspektorat', null], [$v->pejabat, $v->dicatat_oleh]);
        $k = KeputusanVerifikasi::where('verifikasi_id', $v->id)->firstOrFail();
        $this->assertSame([$s[0]->rekomendasi()->id, 'M'], [$k->rekomendasi_id, $k->hasil->value]);
        $this->assertStringContainsString('Sebelumnya juga diverifikasi dengan CHV 7/7/7/77/2025/701', $k->catatan);
        $this->assertSame(0, Verifikasi::where('nomor_surat', '7/7/7/77/2025/701')->count());
        /* Tanpa tanggal LHV: nomornya masuk uraian, bukan surat verifikasi. */
        $this->assertSame(0, Verifikasi::where('nomor_surat', '7/7/7/77/2024/703')->count());
        $this->assertStringContainsString('Nomor CHV: 7/7/7/77/2024/703', $s[1]->uraian_verifikasi);

        $kartu = collect(RiwayatTindakLanjut::susun($s[0]->rekomendasi(), PeranPengguna::SETBA, null));
        $this->assertTrue($kartu->contains(fn ($b) => $b['sumber'] === 'ITJEN' && ($b['nomor'] ?? '') === '7/7/7/77/2026/702'));
        $this->get(route('rekomendasi.show', $s[0]->rekomendasi()))->assertOk()
            ->assertSee('Uraian verifikasi Inspektorat')->assertSee('Surat teguran sudah disampaikan.');
    }

    public function test_tanggal_lhp_diambil_dari_teks_di_file_dan_posisi_mengikuti_hasil(): void
    {
        $kepala = ['No LHP', 'Reff IDT', 'Rekomendasi Temuan Besar', 'Status Verifikasi', 'Status/Posisi Dokumen TL', 'Uraian Verifikasi'];
        $xml = $this->lembar($kepala,
            ['9/LHP/XVII/03/2024', '2024.9.A.1.a', 'Rekomendasi a', 'Memadai', 'Diproses Satker/ SetUnor', ''],
            ['9/LHP/XVII/03/2024', '2024.9.A.1.b)', 'Rekomendasi b', 'Belum Memadai', 'Diproses SiPTL',
                'CHV atas LHPDTT BPK RI No.9/LHP/XVII/03/2024 tanggal 7 Maret 2024 pada BPSDM.'],
            ['28/LHP/XVII/04/2008', '2008.28.A.1.a', 'Rekomendasi c', 'Memadai', 'Selesai', ''],
        );
        $review = app(ReviewHistoris::class);
        $isian = $review->awal((new BacaExcel)->baca($this->excel($xml)->getRealPath()));
        $data = array_column($isian['baris'], 'data');
        $this->assertSame(['2024-03-07', '2024-03-07', ''], array_column($data, 'tgl_surat'));
        $this->assertSame(['tuntas', 'satker', 'tuntas'], array_column($data, 'posisi'));
        $this->assertSame('2024.9.A.1.b', $data[1]['kode']);
        $rows = $review->periksa($isian);
        $peringatan = implode(' ', $rows[1]['warnings']);
        $this->assertStringContainsString('tanggal diambil dari teks di file', $peringatan);
        $this->assertStringContainsString('posisi mengikuti hasil', $peringatan);
        $this->assertStringContainsString('Tanda ")" di akhir Ref IDT', $peringatan);
        $b = PetaKolom::bandingkan($rows[1], $review->pilihan());
        $this->assertSame(['aturan', 'aturan', 'aturan'], [$b['tgl_surat']['keadaan'], $b['posisi']['keadaan'], $b['kode']['keadaan']]);
        /* Tanpa tanggal di mana pun: tetap kosong, tidak dikarang. */
        $this->assertContains('Tanggal surat perlu diisi.', $rows[2]['errors']);
    }
}
