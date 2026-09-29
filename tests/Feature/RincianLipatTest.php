<?php

namespace Tests\Feature;

use App\Models\Rekomendasi;
use App\Models\Sasaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Susunan Rincian rekomendasi sejak 17 Sep.
 *
 * Kata Hizkia: tiap bagian "defaultnya dia bakal tertutup menyisahkan beberapa
 * informasi atau bisa judulnya saja", dan "kombinasikan table SIPTL dengan Table
 * Tindak Lanjut jadi 1 table". Yang dijaga di sini: kartu berlipat dengan
 * ringkasannya, tidak ada lagi kartu Urusan SIPTL tersendiri, urusan SIPTL
 * duduk di baris tabel tindak lanjut — dan batas lihat satuan kerja tetap utuh.
 */
class RincianLipatTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    private function halaman(string $email, Rekomendasi $rek): string
    {
        return $this->masuk($email)->get(route('rekomendasi.show', $rek))->assertOk()->getContent();
    }

    /** Rekomendasi dengan satu baris SS dan satu baris BS — sama dengan contoh Hizkia. */
    private function rekSsBs(): Rekomendasi
    {
        return Rekomendasi::all()->first(function ($r) {
            $st = $r->sasaran->whereNotNull('siptl_tanggal')->map(fn ($x) => $x->status_bpk?->value);

            return $st->contains('SS') && $st->contains('BS');
        });
    }

    public function test_tiap_kartu_berlipat_dan_menyebut_ringkasannya(): void
    {
        $rek = $this->rekSsBs();
        $this->assertNotNull($rek);
        $isi = $this->halaman('setba@contoh.test', $rek);

        /* Laporan asal dan Uraian temuan adalah kartu berlipat. Riwayat status
           dulu kartu berlipat ketiga; sejak 21 Sep ia tab di baris tabel tindak
           lanjut (lihat RiwayatDalamBarisTest). */
        $this->assertGreaterThanOrEqual(2, substr_count($isi, 'data-lipat>'));
        foreach (['Laporan asal', 'Uraian temuan'] as $judul) {
            $this->assertStringContainsString('<b>'.$judul, $isi, $judul.' harus jadi kepala kartu berlipat');
        }
        $this->assertStringNotContainsString('<b>Riwayat status tindak lanjut', $isi);
        $this->assertStringContainsString('data-lipat-ringkas', $isi);
        $this->assertStringContainsString('data-baca-ringkas', $isi);
        /* Kepala rekomendasi: judulnya tetap, keterangannya dibaca selengkapnya. */
        $this->assertStringContainsString('data-baca-alih', $isi);

        /* Arsip rekomendasi kini jadi tombol strategis di bilah atas, yang
           membuka jendelanya. `id`-nya bagian lama kartunya, jadi pemberitahuan yang
           menunjuk arsip mendarat di tombol itu. */
        $this->assertStringContainsString('id="r-arsip" data-buka-arsip', $isi);
        $this->assertStringContainsString('id="arsip" class="tirai tirai-arsip" data-arsip', $isi);
        $this->assertStringContainsString('Arsip rekomendasi', $isi);

        /* Riwayat aktivitas dihapus dari halaman ini. */
        $this->assertStringNotContainsString('Riwayat aktivitas', $isi);
    }

    public function test_urusan_siptl_lebur_ke_tabel_tindak_lanjut(): void
    {
        $rek = $this->rekSsBs();
        $isi = $this->halaman('setba@contoh.test', $rek);
        $teks = strip_tags($isi);

        /* Kartunya sendiri tidak ada lagi. */
        $this->assertStringNotContainsString('<h3>Urusan SIPTL</h3>', $isi);
        $this->assertStringNotContainsString('data-menu-aksi', $isi);

        /* Kepala rincian: hitungan bernama penilainya, kalimat SIPTL bernama,
           dan pembagian uang di kaki. */
        $this->assertStringContainsString('Verifikasi Inspektorat', $teks);
        $this->assertStringContainsString('Penilaian BPK · SIPTL', $teks);
        $this->assertMatchesRegularExpression('/\d+ dari \d+ sesuai\s/', $teks);
        $this->assertStringContainsString('Keadaan SIPTL', $teks);
        $this->assertStringContainsString('ditolak BPK, perlu dikirim ulang', $teks);
        $this->assertStringContainsString('Diakui BPK', $teks);

        /* Tanggal SIPTL menempel di keterangan posisi; putusannya di rincian baris. */
        $this->assertMatchesRegularExpression('/Selesai · dipantau \d{2} \w{3} \d{4}/u', $teks);
        $this->assertStringContainsString('Penilaian BPK lewat SIPTL', $teks);

        /* Pekerjaan SIPTL-nya ada di tab Kerjakan baris yang ditolak. */
        $bs = $rek->sasaran->first(fn ($x) => $x->status_bpk?->value === 'BS');
        $this->assertStringContainsString('data-ada-aksi="1"', $isi);
        $this->assertStringContainsString(route('siptl.ulangBpk', $rek), $isi);
        $this->assertStringContainsString('value="'.$bs->id.'"', $isi);
    }

    public function test_baris_yang_menunggu_bpk_dan_yang_siap_naik_punya_formnya(): void
    {
        $menunggu = Sasaran::where('status_bpk', 'BT')->whereNotNull('siptl_tanggal')->firstOrFail();
        $isi = $this->halaman('setba@contoh.test', $menunggu->tindakan->rekomendasi);
        $this->assertStringContainsString(route('siptl.status', $menunggu), $isi);
        $this->assertStringContainsString('Catat hasil pemantauan BPK', strip_tags($isi));
        $this->assertStringContainsString('Dicatat sekali untuk unggahan ini, lalu terkunci.', strip_tags($isi));

        $siap = Sasaran::whereNull('siptl_tanggal')->get()
            ->first(fn ($x) => $x->perluUnggah($x->tindakan->rekomendasi->jenis()));
        $this->assertNotNull($siap, 'data contoh harus punya baris yang siap diunggah');
        $isi = $this->halaman('setba@contoh.test', $siap->tindakan->rekomendasi);
        $this->assertStringContainsString(route('siptl.unggah', $siap), $isi);
    }

    public function test_satuan_kerja_membaca_putusan_bpk_tanpa_form_dan_tanpa_angka_satker_lain(): void
    {
        $rek = $this->rekSsBs();
        $bs = $rek->sasaran->first(fn ($x) => $x->status_bpk?->value === 'BS');
        $email = \App\Models\User::where('satker_id', $bs->satker_id)->value('email');
        $this->assertNotNull($email);

        $isi = $this->halaman($email, $rek);
        $teks = strip_tags($isi);

        $this->assertStringContainsString('Penilaian BPK lewat SIPTL', $teks);
        $this->assertStringNotContainsString(route('siptl.status', $bs), $isi);
        $this->assertStringNotContainsString(route('siptl.ulangBpk', $rek), $isi);
        /* Pembagian uang menjumlahkan seluruh satuan kerja: bukan urusannya. */
        $this->assertStringNotContainsString('Diakui BPK', $teks);
        /* Satuan kerja lain pada rekomendasi yang sama tidak disebut di tabel. */
        foreach ($rek->sasaran->where('satker_id', '!=', $bs->satker_id) as $lain) {
            $this->assertStringNotContainsString('data-satker="'.$lain->satker_id.'"', $isi);
        }
    }

    public function test_lha_tidak_menggambar_apa_pun_milik_siptl(): void
    {
        $lha = Rekomendasi::all()->first(fn ($r) => ! $r->jenis()->melewatiSiptl());
        $this->assertNotNull($lha);
        $teks = strip_tags($this->halaman('setba@contoh.test', $lha));

        foreach (['Penilaian BPK', 'Keadaan SIPTL', 'Diakui BPK', 'Diunggah ke SIPTL'] as $kata) {
            $this->assertStringNotContainsString($kata, $teks);
        }
    }
}
