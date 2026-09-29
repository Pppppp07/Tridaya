<?php

namespace Tests\Unit;

use App\Enums\HasilTelaah;
use App\Enums\PosisiBerkas;
use App\Enums\StatusTindakLanjut;
use App\Enums\SumberLaporan;
use App\Models\Rekomendasi;
use App\Support\Dasbor;
use App\Support\Tampil;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Aturan yang tidak menyentuh basis data: rangkuman status, posisi paling
 * belakang, hitungan tenggat, dan cara menulis angka.
 *
 * Semuanya disalin dari prototipe, jadi yang diuji di sini sekaligus menjaga
 * dua artefak itu tetap menyebut hal yang sama.
 */
class AturanTest extends TestCase
{
    /* ================= status BPK ================= */

    public function test_rangkuman_status_bpk_dimenangkan_yang_paling_belakang(): void
    {
        $s = fn (array $kode) => Rekomendasi::rangkumBpk(
            array_map(fn ($k) => $k ? StatusTindakLanjut::from($k) : null, $kode))->value;

        $this->assertSame('SS', $s(['SS', 'SS']));
        $this->assertSame('BS', $s(['SS', 'BS']));
        $this->assertSame('BT', $s(['SS', 'BT']));
        /* Baris yang belum diunggah terhitung BT — BPK belum melihat apa pun. */
        $this->assertSame('BT', $s(['SS', null]));
        /* TD tertutup seperti SS; rekomendasinya TD hanya kalau seluruhnya TD. */
        $this->assertSame('TD', $s(['TD', 'TD']));
        $this->assertSame('SS', $s(['TD', 'SS']));
        $this->assertSame('BT', $s([]));
    }

    /* ================= posisi ================= */

    public function test_posisi_paling_belakang_yang_menahan_seluruhnya(): void
    {
        $p = fn (array $kode) => PosisiBerkas::palingBelakang(
            array_map(fn ($k) => PosisiBerkas::from($k), $kode))->value;

        $this->assertSame('satker', $p(['satker', 'uki', 'tuntas']));
        $this->assertSame('uki', $p(['uki', 'inspektorat', 'tuntas']));
        $this->assertSame('tuntas', $p(['tuntas', 'tuntas']));
        /* Tanpa baris sama sekali: masih di satuan kerja, belum bergerak. */
        $this->assertSame('satker', PosisiBerkas::palingBelakang([])->value);
    }

    public function test_sebutan_posisi_menyebut_keadaan_bukan_nama_unit(): void
    {
        $this->assertSame('Menunggu tanggapan satuan kerja', PosisiBerkas::SATKER->label());
        $this->assertSame('Dikembalikan — menunggu dikirim ulang Setba', PosisiBerkas::SETBA_KEMBALI->label());
        $this->assertSame('Sudah selesai diperiksa', PosisiBerkas::TUNTAS->label());
    }

    /* ================= sebutan hasil ================= */

    public function test_sebutan_hasil_ikut_jenis_laporannya(): void
    {
        $this->assertSame('Memadai', HasilTelaah::M->nama(SumberLaporan::LHP));
        $this->assertSame('Sesuai', HasilTelaah::M->nama(SumberLaporan::LHA));
        $this->assertSame('Belum memadai', HasilTelaah::BM->nama(SumberLaporan::LHP));
        $this->assertSame('Belum sesuai', HasilTelaah::BM->nama(SumberLaporan::LHA));

        /* Singkatannya ikut sebutannya: "S", bukan "SS" — dua huruf itu milik
           BPK dan artinya lain. */
        $this->assertSame('M', HasilTelaah::M->kode(SumberLaporan::LHP));
        $this->assertSame('S', HasilTelaah::M->kode(SumberLaporan::LHA));
        $this->assertSame('BS', HasilTelaah::BM->kode(SumberLaporan::LHA));
    }

    /* ================= tenggat ================= */

    public function test_tenggat_lhp_hari_kalender_dan_lha_hari_kerja(): void
    {
        $mulai = Carbon::parse('2026-08-05');

        $this->assertSame('2026-10-04',
            Rekomendasi::hitungTenggat($mulai, SumberLaporan::LHP)->toDateString());

        /* 30 hari kerja dari Rabu 5 Agustus 2026 — akhir pekan dilewati. */
        $lha = Rekomendasi::hitungTenggat($mulai, SumberLaporan::LHA);
        $this->assertSame('2026-09-16', $lha->toDateString());
        $this->assertFalse($lha->isWeekend());
    }

    public function test_selisih_hari_dihitung_dari_tanggalnya_saja(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17 23:30:00'));

        $this->assertSame(12, Rekomendasi::selisih(Carbon::parse('2026-08-05 01:00:00')));
        $this->assertSame(-3, Rekomendasi::selisih(Carbon::parse('2026-08-20')));
        $this->assertNull(Rekomendasi::selisih(null));

        Carbon::setTestNow();
    }

    /* ================= cara menulis ================= */

    public function test_rupiah_ditulis_sama_dengan_prototipe(): void
    {
        $this->assertSame('Rp 18.600.000', Tampil::rupiah(18600000));
        $this->assertSame('—', Tampil::rupiah(0));
        /* rupiahSisa ("lunas" untuk nol) dibuang 27 Sep bersama rpSisa
           prototipe: "lunas" kini menempel di baris Sudah dipulihkan. */

        $this->assertSame('Rp 0', Tampil::rupiahSingkat(0));
        $this->assertSame('Rp 24 jt', Tampil::rupiahSingkat(24000000));
        $this->assertSame('Rp 1.5 M', Tampil::rupiahSingkat(1500000000));
        $this->assertSame('Rp 2 M', Tampil::rupiahSingkat(2000000000));
        $this->assertSame('Rp 500.000', Tampil::rupiahSingkat(500000));
    }

    public function test_tanggal_memakai_singkatan_bulan_prototipe(): void
    {
        $this->assertSame('05 Agu 2026', Tampil::tgl('2026-08-05'));
        $this->assertSame('—', Tampil::tgl(null));
    }

    public function test_lama_telat_dipendekkan_begitu_angkanya_tak_terbayangkan(): void
    {
        $this->assertSame('lewat 12 hari', Tampil::lamaTelat(12));
        $this->assertSame('lewat 4 bulan', Tampil::lamaTelat(120));
        $this->assertSame('lewat setahun', Tampil::lamaTelat(400));
        $this->assertSame('lewat 3 tahun', Tampil::lamaTelat(1200));
    }

    /* ================= hitungan Ringkasan (dasbor) ================= */

    public function test_persen_bulat_dan_aman_terhadap_nol(): void
    {
        $this->assertSame(50, Dasbor::persen(1, 2));
        $this->assertSame(0, Dasbor::persen(3, 0));
        $this->assertSame(33, Dasbor::persen(1, 3));
        $this->assertSame(44, Dasbor::persen(45, 102));
    }

    public function test_skala_sumbu_kelipatan_yang_enak_dibaca(): void
    {
        $this->assertSame(['atas' => 40.0, 'tanda' => [0, 20, 40]], Dasbor::skala(35, 3));
        $this->assertSame(['atas' => 30.0, 'tanda' => [0, 10, 20, 30]], Dasbor::skala(21, 3));
        /* Yang dihitung selalu jumlah: langkahnya paling kecil satu. */
        $this->assertSame(['atas' => 1.0, 'tanda' => [0, 1]], Dasbor::skala(0, 3));
    }

    public function test_pembuka_baku_uraian_dibuang_dari_tampilan(): void
    {
        $this->assertSame('Menagih kelebihan pembayaran',
            Dasbor::pokokUraian('Menteri Pekerjaan Umum agar memerintahkan Kepala BPSDM untuk menagih kelebihan pembayaran'));
        $this->assertSame('Menyetorkan sisa', Dasbor::pokokUraian('Kepala BPSDM agar menyetorkan sisa'));
        $this->assertSame('Uraian tanpa pembuka', Dasbor::pokokUraian('Uraian tanpa pembuka'));
    }

    public function test_dua_belas_bulan_terakhir_ditutup_hari_ini(): void
    {
        $b = Dasbor::bulanTerakhir('2026-08-17', 12);
        $this->assertCount(12, $b);
        $this->assertSame(['kunci' => '2025-09', 'th' => 2025, 'bl' => 8, 'akhir' => '2025-09-30'], $b[0]);
        $this->assertSame(['kunci' => '2026-08', 'th' => 2026, 'bl' => 7, 'akhir' => '2026-08-17'], $b[11]);
        /* Tahun berjalan berhenti di bulan ini. */
        $this->assertCount(8, Dasbor::bulanTahun(2026, '2026-08-17'));
        $this->assertCount(12, Dasbor::bulanTahun(2025, '2026-08-17'));
    }
}
