<?php

namespace Tests\Feature;

use App\Models\Rekomendasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Kepala rincian rekomendasi (27 Sep). Kata Hizkia: "keterangan sisa disini agak
 * membingungkan karena keterangannya lunas tapi di BPK masih ada yang belum
 * diakui", dan satuan kerja yang bagiannya sudah memadai tetap membaca "Belum
 * memadai" — "harusnya statusnya sudah Memadai dan Sesuai khusus untuk Satker
 * yang menangani Tindak Lanjut tersebut".
 */
class KepalaRekomendasiTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    private function halaman(string $akun, string $kode): string
    {
        $rek = Rekomendasi::where('kode', $kode)->firstOrFail();

        return $this->masuk($akun)->get(route('rekomendasi.show', $rek))->assertOk()->getContent();
    }

    /** Isi baris keterangan bernama `$label` (tanpa teks Info-nya). */
    private function nilai(string $isi, string $label): ?string
    {
        if (! preg_match('#<dt>'.preg_quote($label, '#').'(?:(?!</dt>).)*</dt>\s*<dd>(.*?)</dd>#su', $isi, $m)) {
            return null;
        }

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($m[1]))));
    }

    public function test_uang_berbaris_yang_sudah_dan_yang_belum(): void
    {
        $isi = $this->halaman('setba@contoh.test', '2023.16.I.6.a');

        $this->assertSame('Rp 111.000.000', $this->nilai($isi, 'Nilai yang harus dipulihkan'));
        $this->assertSame('Rp 111.000.000 · lunas', $this->nilai($isi, 'Sudah dipulihkan'));
        $this->assertSame('Rp 84.000.000 · Rp 27.000.000 belum diakui', $this->nilai($isi, 'Diakui BPK'));
        /* Baris lama yang mencampur dua ukuran tidak ada lagi. */
        $this->assertNull($this->nilai($isi, 'Sisa'));
        $this->assertNull($this->nilai($isi, 'Belum diakui BPK'));
        $this->assertNull($this->nilai($isi, 'Nilai yang dipulihkan'));

        /* Ringkasan tertutup: yang masih tertinggal saja. */
        $ringkas = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(
            \Illuminate\Support\Str::betweenFirst($isi, 'data-baca-ringkas', 'data-baca-rinci'))));
        $this->assertStringContainsString('Pemulihan lunas', $ringkas);
        $this->assertStringContainsString('Belum diakui BPK Rp 27.000.000', $ringkas);
        $this->assertStringNotContainsString('Sisa nilai', $ringkas);
    }

    public function test_satuan_kerja_membaca_keadaan_tindak_lanjutnya_sendiri(): void
    {
        /* Baris Pusbangkom SDACKPS sudah memadai dan SS; baris Sekretariat
           BPSDM belum diputus — rekomendasinya sendiri belum memadai. */
        $satker = $this->halaman('sdackps', '2024.13.I.7.b');
        $this->assertSame('Memadai', $this->nilai($satker, 'Status verifikasi'));
        $this->assertMatchesRegularExpression('/1 dari 1 memadai\s*M\b/u',
            $this->nilai($satker, 'Verifikasi Inspektorat') ?? '');
        $this->assertMatchesRegularExpression('/1 dari 1 sesuai\s*SS\b/u',
            $this->nilai($satker, 'Penilaian BPK · SIPTL') ?? '');

        $setba = $this->halaman('setba@contoh.test', '2024.13.I.7.b');
        $this->assertSame('Belum memadai', $this->nilai($setba, 'Status verifikasi'));

        /* Lencana di pencarian atas mengikuti aturan yang sama. */
        $hasil = $this->masuk('sdackps')->getJson(route('cari', ['q' => '2024.13.I.7.b']))->assertOk()->json('rek');
        $this->assertNotEmpty($hasil);
        $this->assertStringContainsString('SS · Sudah sesuai', $hasil[0]['cap']);
    }

    /**
     * Kata Hizkia (28 Sep): "jangan terlalu Personal pakai kata saya … hapus
     * keterangan Draft dan juga Catatan setba dalam mode ringkas dan hapus
     * Keterangan Draft pada mode detail". Catatan Setba tetap terbaca di
     * mode rincian.
     */
    public function test_kepala_tanpa_draf_tanpa_catatan_ringkas_dan_tanpa_kata_saya(): void
    {
        $draf = \App\Models\DrafTanggapan::with('sasaran.tindakan', 'sasaran.satker.penanggungJawab')->get()
            ->first(fn ($d) => $d->sasaran->tindakan && $d->sasaran->satker?->penanggungJawab);
        $this->assertNotNull($draf, 'Data contoh punya draf tanggapan.');
        $draf->sasaran->tindakan->forceFill(['catatan' => 'Lengkapi bukti setor bulan Agustus.'])->save();
        $rek = $draf->sasaran->tindakan->rekomendasi;

        foreach (['setba@contoh.test', $draf->sasaran->satker->penanggungJawab->email] as $akun) {
            $isi = $this->masuk($akun)->get(route('rekomendasi.show', $rek))->assertOk()->getContent();
            $kepala = \Illuminate\Support\Str::betweenFirst($isi, 'id="r-kepala"', 'class="kepalatl"');
            $ringkas = preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(
                \Illuminate\Support\Str::betweenFirst($kepala, 'data-baca-ringkas', 'data-baca-rinci'))));
            $rinci = html_entity_decode(strip_tags(\Illuminate\Support\Str::after($kepala, 'data-baca-rinci')));

            $this->assertStringNotContainsString('Draf', $ringkas, $akun);
            $this->assertStringNotContainsString('Catatan Setba', $ringkas, $akun);
            $this->assertStringNotContainsString('Draf belum dikirim', $rinci, $akun);
            $this->assertStringContainsString('Catatan Setba untuk satuan kerja', $rinci, $akun);
            $this->assertStringContainsString('Lengkapi bukti setor bulan Agustus.', $rinci, $akun);
            $this->assertDoesNotMatchRegularExpression('/\bsaya\b/iu', strip_tags($kepala), $akun);
            $this->assertStringContainsString('Rincian tindak lanjut', $isi, $akun);
            $this->assertNotNull($this->nilai($isi, 'Bentuk tindak lanjut'), $akun);
        }
        /* Bagi satuan kerja: satuan kerjanya sendiri, tanpa "saya". */
        $this->assertSame($draf->sasaran->satker->namaPendek(), $this->nilai($isi, 'Satuan kerja'));
    }
}
