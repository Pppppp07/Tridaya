<?php

namespace Tests\Feature;

use App\Models\ItemPermintaan;
use App\Models\Lampiran;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Rincian tindak lanjut sejak 20 Sep.
 *
 * Kata Hizkia, melihat "Bukti yang sudah dikirim" berdampingan dengan
 * "Kelengkapan dokumen yang diminta": "menampilkan informasi berulang, jadi
 * gabungkan tampilannya jadi satu bagian informasi saja", dengan satu
 * kekecualian — bagian dokumen tambahan, yang "hanya ditampilkan jika ada
 * dokumen tambahan saja". Judul temuan juga pindah ke kartu Uraian temuan.
 */
class RincianBuktiTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    private function halaman(Rekomendasi $r): string
    {
        return $this->masuk('setba@contoh.test')
            ->get(route('rekomendasi.show', $r))->assertOk()->getContent();
    }

    /** Baris yang seluruh dokumennya sudah terlampir. */
    private function barisLengkap(): Sasaran
    {
        $item = ItemPermintaan::where('terpenuhi', true)->get()
            ->first(fn ($i) => $i->lampiran->isNotEmpty());
        $this->assertNotNull($item, 'data contoh harus punya dokumen yang sudah terlampir');

        /* Permintaan dokumen menunjuk barisnya langsung — satu satuan kerja
           pada satu bentuk tindak lanjut. */
        return $item->permintaan->sasaran;
    }

    public function test_dokumen_yang_diminta_dan_buktinya_jadi_satu_daftar(): void
    {
        $x = $this->barisLengkap();
        $r = $x->tindakan->rekomendasi;
        $minta = $r->permintaanUntuk($x->satker_id, $x->tindakan_id)->flatMap->item;
        $isi = $this->halaman($r);
        $teks = strip_tags($isi);

        /* Satu bagian, bukan dua yang menyebut hal yang sama. */
        $this->assertStringContainsString('Dokumen yang diminta', $teks);
        $this->assertStringNotContainsString('Kelengkapan dokumen yang diminta', $teks);
        $this->assertStringNotContainsString('ceklisrapat', $isi);
        $this->assertMatchesRegularExpression('/\d+ dari \d+ sudah terlampir/', $teks);

        /* Tiap butir yang terlampir tampil sebagai berkasnya sendiri, sekali —
           bukan sekali sebagai berkas lalu sekali lagi sebagai ceklis. Dihitung
           di dalam bagiannya sendiri: jendela Arsip mendaftar berkas yang sama
           untuk keperluan lain. Sejak 25 Sep bagian itu satu baris berlabel
           (`data-rinci="dokumen"`) yang ditutup hitungannya sendiri. */
        $bagian = collect(explode('data-rinci="dokumen"', $isi))->slice(1)
            ->map(fn ($p) => Str::before($p, 'sudah terlampir</span>'));
        $this->assertTrue($bagian->isNotEmpty());
        foreach ($minta->where('terpenuhi', true) as $butir) {
            $berkas = $butir->lampiran->first();
            $this->assertNotNull($berkas);
            $baris = '<span class="nama">'.e($berkas->labelTampil()).'</span>';
            $this->assertSame(1, $bagian->sum(fn ($b) => substr_count($b, $baris)),
                $butir->nama.' harus tampil satu baris saja');
            $this->assertSame(1, $bagian->sum(fn ($b) => substr_count(strip_tags($b), $butir->nama)),
                $butir->nama.' harus disebut sekali saja');
        }
    }

    public function test_dokumen_yang_belum_dilampirkan_tetap_berbaris(): void
    {
        $butir = ItemPermintaan::where('terpenuhi', false)->firstOrFail();
        $isi = $this->halaman($butir->permintaan->rekomendasi);

        /* Sejak 25 Sep daftar centang di kartu Bukti & tanggapan. */
        $this->assertStringContainsString('<li class="belum">', $isi);
        $this->assertStringContainsString('belum dilampirkan', strip_tags($isi));
        $this->assertStringContainsString($butir->nama, $isi);
    }

    /**
     * Bagian "Dokumen tambahan" hanya ada kalau memang ada kiriman di luar
     * yang diminta.
     */
    public function test_dokumen_tambahan_muncul_hanya_kalau_ada(): void
    {
        $x = $this->barisLengkap();
        $r = $x->tindakan->rekomendasi;

        $this->assertStringNotContainsString('Dokumen tambahan', strip_tags($this->halaman($r)));

        Lampiran::create([
            'rekomendasi_id' => $r->id,
            'sasaran_id'     => $x->id,
            'tindakan_id'    => $x->tindakan_id,
            'label_jenis'    => 'Notulen rapat penertiban',
            'nama_asli'      => 'Notulen rapat penertiban — '.$x->satker->namaPendek(),
            'link'         => 'https://contoh.test/notulen.pdf',
            'label_oleh'     => $x->satker->namaPendek(),
            'diunggah_pada'  => now(),
        ]);

        $teks = strip_tags($this->halaman($r->fresh()));
        $this->assertStringContainsString('Dokumen tambahan', $teks);
        $this->assertStringContainsString('Notulen rapat penertiban — '.$x->satker->namaPendek(), $teks);
    }

    /**
     * Judul temuan tempatnya di kartu Uraian temuan, bukan di kepala
     * rekomendasi — dan kartu yang sedang terlipat pun menyebutnya.
     */
    public function test_judul_temuan_pindah_ke_uraian_temuan(): void
    {
        $r = Rekomendasi::with('temuan')->firstOrFail();
        $tem = $r->temuan;
        $isi = $this->halaman($r);

        $kepala = Str::between($isi, '<div id="r-kepala"', '<h2>');
        $this->assertStringNotContainsString($tem->judul, $kepala);
        $this->assertStringNotContainsString('jdltem', $isi);

        /* Berbaris bersama keterangan temuan yang lain, bukan blok tersendiri. */
        $judul = Str::between($isi, '<dt>Judul temuan</dt>', '</dd>');
        $this->assertStringContainsString(e($tem->judul), $judul);
        /* Ditulis seperti di surat: satu baris, dipisah garis miring. */
        $nomor = Str::between($isi, '<dt>Nomor/Kode temuan', '</dd>');
        $this->assertStringContainsString($tem->nomor_pada_surat.'/'.$tem->kode, $nomor);

        /* Ringkasan kartu yang terlipat menyebut judulnya lebih dulu. */
        $this->assertStringContainsString('<span class="potong">'.e($tem->judul).'</span>', $isi);
    }
}
