<?php

namespace Tests\Feature;

use App\Models\Lampiran;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Models\Satker;
use App\Support\Terlihat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Apa yang boleh dan tidak boleh dilihat satuan kerja.
 *
 * Angka yang bocor jauh lebih sulit disadari daripada halaman yang bocor, jadi
 * yang diuji bukan cuma "halamannya ditolak" melainkan juga isinya: nama satuan
 * kerja lain di dalam kalimat, nilai yang bukan bagiannya, dan surat
 * pemeriksaan asli yang memuat temuan semua orang.
 */
class HakAksesSatkerTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    /** Rekomendasi yang dipikul beberapa satuan kerja, salah satunya `$satkerId`. */
    private function rekBersama(int $satkerId): Rekomendasi
    {
        $rek = Rekomendasi::with('sasaran.satker')->get()
            ->first(fn ($r) => $r->daftarSasaran()->contains('satker_id', $satkerId)
                && $r->daftarSasaran()->pluck('satker_id')->unique()->count() > 1);
        $this->assertNotNull($rek, 'Data contoh perlu memuat rekomendasi yang dipikul bersama.');

        return $rek;
    }

    public function test_hanya_barisnya_sendiri_yang_terlihat(): void
    {
        $pengguna = $this->akun('medan');
        $rek = $this->rekBersama($pengguna->satker_id);

        /* Yang dipakai layar: baris milik satuan kerja yang sedang masuk saja,
           sementara keadaan seluruh rekomendasi tetap dihitung dari semuanya. */
        $milik = Rekomendasi::with('sasaran.satker')->find($rek->id);
        $dipangkas = Terlihat::untuk($pengguna)->pangkasRekomendasi($milik);

        foreach ($dipangkas->daftarSasaran() as $baris) {
            $this->assertSame($pengguna->satker_id, $baris->satker_id);
        }
        $this->assertGreaterThan($dipangkas->daftarSasaran()->count(), $dipangkas->semuaBaris()->count());
    }

    public function test_rincian_tidak_menyebut_satuan_kerja_lain(): void
    {
        $pengguna = $this->akun('medan');
        $rek = Rekomendasi::with('sasaran.satker')->get()
            ->first(fn ($r) => $r->daftarSasaran()->contains('satker_id', $pengguna->satker_id)
                && $r->daftarSasaran()->pluck('satker_id')->unique()->count() > 1);
        $this->assertNotNull($rek, 'Data contoh perlu memuat rekomendasi yang dipikul bersama.');

        $lain = Satker::whereNot('id', $pengguna->satker_id)
            ->whereIn('id', $rek->daftarSasaran()->pluck('satker_id'))->get();

        $halaman = $this->actingAs($pengguna)->get(route('rekomendasi.show', $rek))->assertOk();

        foreach ($lain as $s) {
            $halaman->assertDontSee($s->nama_pendek);
            $halaman->assertDontSee($s->nama);
        }
    }

    public function test_surat_pemeriksaan_asli_tidak_sampai_ke_satuan_kerja(): void
    {
        $pengguna = $this->akun('medan');
        $rek = Rekomendasi::with('sasaran')->get()
            ->first(fn ($r) => $r->daftarSasaran()->contains('satker_id', $pengguna->satker_id));

        $asli = Lampiran::where('rekomendasi_id', $rek->id)->where('surat_asli', true)->first();
        $this->assertNotNull($asli, 'Tiap rekomendasi data contoh membawa surat aslinya.');

        /* Tidak digambar di halaman rinciannya … */
        $this->actingAs($pengguna)->get(route('rekomendasi.show', $rek))
            ->assertOk()->assertDontSee($asli->nama_asli);

        /* … dan tidak bisa dibuka lewat alamatnya sendiri. */
        $this->actingAs($pengguna)->get(route('berkas.show', $asli))->assertForbidden();

        /* Setba tetap boleh. */
        $this->masuk('setba@contoh.test')->get(route('berkas.show', $asli))->assertOk();
    }

    public function test_berkas_satuan_kerja_lain_ditolak(): void
    {
        $pengguna = $this->akun('medan');
        $lain = Lampiran::whereNotNull('sasaran_id')->where('surat_asli', false)->get()
            ->first(fn ($b) => $b->sasaran?->satker_id !== $pengguna->satker_id);
        $this->assertNotNull($lain);

        $this->actingAs($pengguna)->get(route('berkas.show', $lain))->assertForbidden();
    }

    public function test_rekomendasi_yang_bukan_urusannya_tidak_bisa_dibuka(): void
    {
        $pengguna = $this->akun('medan');
        $bukan = Rekomendasi::with('sasaran')->get()
            ->first(fn ($r) => ! $r->daftarSasaran()->contains('satker_id', $pengguna->satker_id));

        $this->actingAs($pengguna)->get(route('rekomendasi.show', $bukan))->assertForbidden();
    }

    public function test_gerak_berkas_milik_satuan_kerja_lain_ditolak(): void
    {
        $pengguna = $this->akun('medan');
        $baris = Sasaran::with('satker')->get()
            ->first(fn ($x) => $x->satker_id !== $pengguna->satker_id);

        $this->actingAs($pengguna)
            ->post(route('tanggapan.simpan', $baris), ['aksi' => 'draf', 'uraian' => 'coba'])
            ->assertForbidden();
    }

    public function test_daftar_laporan_hanya_memuat_yang_menyangkutnya(): void
    {
        $pengguna = $this->akun('medan');
        $daftar = Terlihat::untuk($pengguna)->daftarLaporan();

        foreach ($daftar as $lap) {
            $kena = $lap->temuan->contains(fn ($t) => $t->mengenai($pengguna->satker_id)
                || $t->rekomendasi->contains(fn ($r) => $r->dituju($pengguna->satker_id)));
            $this->assertTrue($kena, "Laporan {$lap->nomor} tidak menyangkut satuan kerja ini.");
        }
    }
}
