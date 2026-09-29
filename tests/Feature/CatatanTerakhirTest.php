<?php

namespace Tests\Feature;

use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Support\RiwayatTindakLanjut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Kolom "Catatan terakhir" di tabel tindak lanjut, sejak 21 Sep.
 *
 * Kata Hizkia: "catatannya berdasarkan catatan riwayat tindak lanjut saja, dan
 * keterangan langkah berikutnya dihilangkan saja". Jadi isinya kolom Catatan
 * pada baris "Terbaru" di tab Riwayat baris itu — tidak ditimpa, cuma dibaca
 * dari riwayat yang menyimpan semuanya.
 */
class CatatanTerakhirTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    private function halaman(string $akun, Rekomendasi $r): string
    {
        return $this->masuk($akun)->get(route('rekomendasi.show', $r))->assertOk()->getContent();
    }

    /** Isi sel kolom Catatan terakhir, urut seperti barisnya. */
    private function sel(string $isi): array
    {
        return collect(explode('data-catatan-terakhir>', $isi))->slice(1)
            ->map(fn ($p) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(Str::before($p, '</td>'))))))
            ->values()->all();
    }

    public function test_isinya_baris_terbaru_riwayat_tiap_satuan_kerja(): void
    {
        /* Empat satuan kerja, keempatnya pernah ditolak — tiap baris punya
           catatan dari penilai yang berbeda. */
        $r = Rekomendasi::where('kode', '2026.22.I.1.a')->firstOrFail();
        $isi = $this->halaman('setba@contoh.test', $r);

        $this->assertStringContainsString('>Catatan terakhir</th>', $isi);
        $sel = $this->sel($isi);
        $this->assertCount(4, $sel);

        $u = $this->akun('setba@contoh.test');
        $kartu = RiwayatTindakLanjut::kartu($r, $u->peran, $u->satker_id);
        foreach ($r->semuaBaris() as $x) {
            $terbaru = collect($kartu)->firstWhere('kunci', $x->tindakan_id.'|'.$x->satker_id)['baris'][0];
            $this->assertTrue(collect($sel)->contains(fn ($s) => str_starts_with($s, $terbaru['catatan'])),
                $x->satker->namaPendek().' harus membaca catatan terbarunya');
        }

        /* Sumbernya yang disebut, bukan pengetiknya: putusan Inspektorat
           diketik Setba, tapi di bawahnya tertulis Itjen. */
        $this->assertContains('Nomor kuitansi tidak berurutan dan dua kuitansi bertanggal sama. Itjen · 31 Jul 2026', $sel);
    }

    public function test_baris_yang_belum_punya_riwayat_bertanda_strip(): void
    {
        /* Laporan 44/LHP: belum satu pun satuan kerjanya menyentuh. */
        $r = Rekomendasi::where('kode', '2026.44.I.1.a')->firstOrFail();
        $isi = $this->halaman('jakarta', $r);

        $this->assertSame(['—'], $this->sel($isi));
        $this->assertStringContainsString('title="Belum ada peristiwa di riwayat tindak lanjut"', $isi);
    }

    public function test_satuan_kerja_cuma_membaca_catatan_barisnya_sendiri(): void
    {
        $r = Rekomendasi::where('kode', '2026.22.I.1.a')->firstOrFail();
        $isi = $this->halaman('medan', $r);

        $this->assertCount(1, $this->sel($isi));
        $this->assertStringStartsWith('Ditolak UKI: Kuitansi tidak bermeterai', $this->sel($isi)[0]);
        /* Catatan penolakan Jakarta tidak boleh terbaca Medan. */
        $this->assertStringNotContainsString('Nomor kuitansi tidak berurutan', $isi);
    }

    public function test_kalimat_langkah_berikutnya_sudah_dibuang(): void
    {
        $r = Rekomendasi::where('kode', '2026.22.I.1.a')->firstOrFail();
        $isi = $this->halaman('setba@contoh.test', $r);

        $this->assertStringNotContainsString('tl-petunjuk', $isi);
        $this->assertStringNotContainsString('Periksa alasan penolakan dan dokumen yang diminta, lalu kirim ulang ke satuan kerja.', $isi);

        $x = Sasaran::firstOrFail();
        $this->assertArrayNotHasKey('langkah', $x->presentasi($r->jenis()));
    }
}
