<?php

namespace Tests\Feature;

use App\Enums\PosisiBerkas;
use App\Models\DrafTanggapan;
use App\Models\Laporan;
use App\Models\Notifikasi;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Models\Satker;
use App\Models\Temuan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Penjaga kewarasan data contoh.
 *
 * Data contoh yang tidak masuk akal membuat seluruh tampilan di atasnya ikut
 * tidak bisa dipercaya — dan yang paling berbahaya, salahnya tidak kelihatan
 * sampai ada orang yang membaca angkanya sungguh-sungguh.
 *
 * Angkanya sama persis dengan yang diperagakan prototipe: 26 laporan, 29
 * temuan, 49 rekomendasi, 102 penugasan. Sejak 21 Sep, 22 penugasan di
 * antaranya belum disentuh satuan kerjanya sama sekali — sebelumnya cuma 5,
 * dan Hizkia mencatat data contohnya "rata rata yang sudah menunggu dan
 * selesai".
 */
class DataContohTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    public function test_ukurannya_sama_dengan_prototipe(): void
    {
        $this->assertSame(26, Laporan::count());
        $this->assertSame(29, Temuan::count());
        $this->assertSame(49, Rekomendasi::count());
        $this->assertSame(102, Sasaran::count());
        $this->assertSame(16, Satker::count());
        /* Enam akun peran pusat (Setba, UKI, Inspektorat, Pimpinan, DTI,
           Admin) ditambah satu akun tiap satuan kerja. */
        $this->assertSame(22, User::count());
        /* Semuanya terhubung ke pegawai di direktori — masuk lewat SSO
           mengenali orang dari NIP. */
        $this->assertSame(0, User::whereNull('nip')->count());
    }

    public function test_tidak_ada_tanggal_di_masa_depan(): void
    {
        $ini = now()->toDateString();

        foreach (Laporan::all() as $l) {
            $this->assertLessThanOrEqual($ini, $l->tgl_surat->toDateString(), "Laporan {$l->nomor}");
            $this->assertLessThanOrEqual($ini, $l->tgl_terima->toDateString(), "Laporan {$l->nomor}");
            $this->assertGreaterThanOrEqual($l->tgl_surat->toDateString(), $l->tgl_terima->toDateString(),
                "Laporan {$l->nomor} diterima sebelum suratnya dibuat");
        }

        foreach (Sasaran::whereNotNull('siptl_tanggal')->get() as $x) {
            $this->assertLessThanOrEqual($ini, $x->siptl_tanggal->toDateString());
        }
    }

    public function test_tiap_rekomendasi_punya_baris_dan_kode_yang_tidak_kembar(): void
    {
        $kode = Rekomendasi::pluck('kode');
        $this->assertSame($kode->count(), $kode->unique()->count(), 'Ada kode rekomendasi yang kembar.');

        foreach (Rekomendasi::with('sasaran')->get() as $r) {
            $this->assertNotEmpty($r->daftarSasaran(), "Rekomendasi {$r->kode} tidak punya penugasan.");
        }
    }

    public function test_nilai_rekomendasi_sama_dengan_jumlah_bagiannya(): void
    {
        foreach (Rekomendasi::with('sasaran')->get() as $r) {
            $this->assertSame((int) $r->nilai_pulih, $r->nilaiRek(),
                "Nilai tersimpan rekomendasi {$r->kode} berbeda dari jumlah bagiannya.");
        }
    }

    public function test_hanya_lhp_yang_punya_status_bpk(): void
    {
        foreach (Sasaran::with('tindakan.rekomendasi.temuan.laporan')->get() as $x) {
            if ($x->status_bpk === null && $x->siptl_tanggal === null) {
                continue;
            }
            $this->assertTrue($x->tindakan->rekomendasi->jenis()->melewatiSiptl(),
                'Baris LHA tidak boleh punya urusan SIPTL.');
        }
    }

    public function test_baris_yang_sudah_naik_ke_siptl_pasti_sudah_tuntas(): void
    {
        foreach (Sasaran::whereNotNull('siptl_tanggal')->get() as $x) {
            $this->assertSame(PosisiBerkas::TUNTAS, $x->pos(),
                'Baris yang diunggah ke SIPTL harus sudah selesai diperiksa.');
        }
    }

    public function test_penyapu_draf_sudah_berjalan_sekali(): void
    {
        /* Penyemai memanggil `tlhp:kirim-draf`, jadi draf yang belum dikirim lewat
           tujuh hari sudah terkirim — sama seperti prototipe yang menyapunya
           saat aplikasi dibuka. */
        $sapuan = Notifikasi::where('aksi', 'like', 'Terkirim otomatis%')->count();
        $this->assertGreaterThan(0, $sapuan);

        foreach (DrafTanggapan::with('sasaran')->get() as $draf) {
            $this->assertNotNull($draf->sasaran, 'Draf tanggapan tanpa barisnya.');
        }
    }

    public function test_perintah_penyapu_aman_dijalankan_ulang(): void
    {
        $sebelum = Notifikasi::count();

        Artisan::call('tlhp:kirim-draf');

        /* Yang sudah terkirim tidak dikirim dua kali. */
        $this->assertSame($sebelum, Notifikasi::count());
    }
}
