<?php

namespace Tests\Unit;

use App\Enums\PeranPengguna;
use App\Enums\SumberLaporan;
use App\Models\Laporan;
use App\Models\Pengembalian;
use App\Models\Rekomendasi;
use App\Models\RiwayatStatus;
use App\Models\Sasaran;
use App\Models\Satker;
use App\Models\Surat;
use App\Models\Temuan;
use App\Models\Tindakan;
use App\Support\RiwayatTindakLanjut;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

/** Warna perjalanan harus membaca penilaian historis, bukan keadaan terkini. */
class RiwayatJalurTest extends TestCase
{
    private function rek(SumberLaporan $jenis = SumberLaporan::LHP): Rekomendasi
    {
        $laporan = new Laporan(['sumber' => $jenis]);
        $temuan = (new Temuan)->setRelation('laporan', $laporan);
        $rek = (new Rekomendasi)->setRelation('temuan', $temuan);
        $tindakan = (new Tindakan)->forceFill(['id' => 11])->setRelation('bentuk', null);
        $satker = (new Satker)->forceFill(['id' => 12, 'nama' => 'Satuan kerja contoh']);
        $baris = (new Sasaran(['tindakan_id' => 11, 'satker_id' => 12]))->forceFill(['id' => 13])
            ->setRelation('tindakan', $tindakan)->setRelation('satker', $satker)
            ->setRelation('riwayatStatus', new Collection);
        $rek->setRelation('sasaran', new Collection([$baris]));
        foreach (['keputusan', 'telaah', 'pengembalian', 'tanggapan', 'surat'] as $relasi) {
            $rek->setRelation($relasi, new Collection);
        }

        return $rek;
    }

    private function catatStatus(Rekomendasi $rek, string $sumber, string $ke, string $tanggal, ?string $dari = null): void
    {
        $baris = $rek->sasaran->first();
        $id = $baris->riwayatStatus->count() + 1;
        $baris->riwayatStatus->push((new RiwayatStatus([
            'sumber' => $sumber, 'dari' => $dari, 'ke' => $ke, 'tanggal' => $tanggal,
        ]))->forceFill(['id' => $id, 'created_at' => $tanggal.' 10:00:'.str_pad((string) $id, 2, '0', STR_PAD_LEFT)]));
    }

    private function teruskan(Rekomendasi $rek, string $ke, string $tanggal): void
    {
        $rek->surat->push((new Surat(['sasaran_id' => 13, 'ke' => $ke, 'tanggal' => $tanggal]))
            ->forceFill(['id' => $rek->surat->count() + 1, 'created_at' => $tanggal.' 09:00:00'])
            ->setRelation('sasaran', $rek->sasaran->first()));
    }

    private function kembali(Rekomendasi $rek, string $dari, string $tanggal): void
    {
        $rek->pengembalian->push((new Pengembalian(['sasaran_id' => 13, 'dari' => $dari, 'tanggal' => $tanggal]))
            ->forceFill(['id' => $rek->pengembalian->count() + 1, 'created_at' => $tanggal.' 11:00:00'])
            ->setRelation('sasaran', $rek->sasaran->first()));
    }

    private function kartu(Rekomendasi $rek): array
    {
        return RiwayatTindakLanjut::kartu($rek, PeranPengguna::SETBA, null)[0];
    }

    public function test_hasil_menempel_pada_penilai_dan_tanggal_kedatangan_tidak_ditimpa(): void
    {
        $rek = $this->rek();
        $this->teruskan($rek, 'UKI', '2026-01-01');
        $this->catatStatus($rek, 'UKI', 'M', '2026-01-02');
        $this->teruskan($rek, 'Inspektorat', '2026-01-03');
        $this->catatStatus($rek, 'Itjen', 'BM', '2026-01-04');
        $jalur = $this->kartu($rek)['jalur'];

        $this->assertSame(['Satker', 'UKI', 'Setba', 'Inspektorat', 'Setba'], array_column($jalur, 'tempat'));
        $this->assertSame('2026-01-01', $jalur[1]['tanggal']);
        $this->assertSame('2026-01-02', $jalur[1]['hasil']['tanggal']);
        $this->assertSame('ss', $jalur[1]['hasil']['kelas']);
        $this->assertSame('bs', $jalur[3]['hasil']['kelas']);
        $this->assertArrayNotHasKey('hasil', $jalur[2]);
        $this->assertArrayNotHasKey('hasil', $jalur[4]);
    }

    public function test_penolakan_dan_penilaian_ulang_tetap_dua_kunjungan_dengan_hasil_sendiri(): void
    {
        $rek = $this->rek();
        $this->teruskan($rek, 'UKI', '2026-01-01');
        $this->catatStatus($rek, 'UKI', 'BM', '2026-01-02');
        $this->kembali($rek, 'UKI', '2026-01-03');
        $this->teruskan($rek, 'UKI', '2026-01-04');
        $this->catatStatus($rek, 'UKI', 'M', '2026-01-05');
        $kartu = $this->kartu($rek);
        $uki = array_values(array_filter($kartu['jalur'], fn ($j) => $j['tempat'] === 'UKI'));

        $this->assertSame(1, $kartu['periodeKini']);
        $this->assertCount(2, $uki);
        $this->assertSame(['BM', 'M'], array_column(array_column($uki, 'hasil'), 'kode'));
        $this->assertSame(['bs', 'ss'], array_column(array_column($uki, 'hasil'), 'kelas'));
        $this->assertSame('2026-01-04', $uki[1]['tanggal']);
    }

    public function test_unggah_bt_menunggu_penilaian_dan_bukan_putusan(): void
    {
        $rek = $this->rek();
        $this->catatStatus($rek, 'SIPTL', 'BT', '2026-01-01');
        $bpk = $this->kartu($rek)['jalur'][1];

        $this->assertSame('BPK', $bpk['tempat']);
        $this->assertSame('Menunggu penilaian BPK', $bpk['hasil']['nama']);
        $this->assertSame('bt', $bpk['hasil']['kelas']);
        $this->assertFalse($bpk['hasil']['penilaian']);

        $this->catatStatus($rek, 'SIPTL', 'SS', '2026-01-02', 'BT');
        $jalur = $this->kartu($rek)['jalur'];
        $this->assertSame('2026-01-01', $jalur[1]['tanggal']);
        $this->assertSame('SS', $jalur[1]['hasil']['kode']);
        $this->assertTrue($jalur[1]['hasil']['penilaian']);
        $this->assertSame('Selesai', $jalur[2]['tempat']);
        $this->assertSame($jalur[1]['hasil'], $jalur[2]['hasil']);
    }

    public function test_pengembalian_bpk_tidak_mengubah_hasil_bs_periode_lama(): void
    {
        $rek = $this->rek();
        $this->catatStatus($rek, 'SIPTL', 'BT', '2026-01-01');
        $this->catatStatus($rek, 'SIPTL', 'BS', '2026-01-02', 'BT');
        $this->catatStatus($rek, 'SIPTL', 'BT', '2026-01-03', 'BS');
        $this->catatStatus($rek, 'SIPTL', 'BT', '2026-02-01');
        $this->catatStatus($rek, 'SIPTL', 'SS', '2026-02-02', 'BT');
        $kartu = $this->kartu($rek);
        $lama = collect($kartu['babak'])->firstWhere('k', 1)['jalur'];

        $this->assertSame(2, $kartu['periodeKini']);
        $this->assertSame('BS', $lama[1]['hasil']['kode']);
        $this->assertSame('2026-01-02', $lama[1]['hasil']['tanggal']);
        $this->assertSame('SS', $kartu['jalur'][1]['hasil']['kode']);
        $this->assertSame('2026-02-01', $kartu['jalur'][1]['tanggal']);
    }

    public function test_lha_berbeda_periode_dan_pakai_sebutan_sesuai(): void
    {
        $rek = $this->rek(SumberLaporan::LHA);
        $this->catatStatus($rek, 'Itjen', 'BM', '2026-01-01');
        $this->catatStatus($rek, 'Itjen', 'M', '2026-02-01', 'BM');
        $kartu = $this->kartu($rek);
        $lama = collect($kartu['babak'])->firstWhere('k', 1)['jalur'];

        $this->assertSame(2, $kartu['periodeKini']);
        $this->assertSame('BS', $lama[1]['hasil']['kode']);
        $this->assertSame('Belum sesuai', $lama[1]['hasil']['nama']);
        $this->assertSame('S', $kartu['jalur'][1]['hasil']['kode']);
        $this->assertSame('Sesuai', $kartu['jalur'][1]['hasil']['nama']);
        $this->assertSame('Selesai', $kartu['jalur'][2]['tempat']);
    }

    public function test_data_lama_dengan_putusan_tanpa_penerusan_memakai_tanggal_penilaian_nyata(): void
    {
        $rek = $this->rek();
        $this->catatStatus($rek, 'UKI', 'M', '2026-01-01');
        $this->catatStatus($rek, 'Itjen', 'M', '2026-01-02');
        $this->catatStatus($rek, 'SIPTL', 'SS', '2026-01-03');
        $jalur = $this->kartu($rek)['jalur'];

        $this->assertSame(['Satker', 'UKI', 'Setba', 'Inspektorat', 'Setba', 'BPK', 'Selesai'], array_column($jalur, 'tempat'));
        $this->assertSame('2026-01-01', $jalur[1]['tanggal']);
        $this->assertSame('rs-1', $jalur[1]['hasil']['peristiwa']);
        $this->assertSame('2026-01-02', $jalur[3]['tanggal']);
        $this->assertSame('2026-01-03', $jalur[5]['tanggal']);
    }

    public function test_penerusan_saja_tidak_meminjam_hasil_terkini_barisan(): void
    {
        $rek = $this->rek();
        $rek->sasaran->first()->hasil_uki = 'M';
        $this->teruskan($rek, 'UKI', '2026-01-01');
        $jalur = $this->kartu($rek)['jalur'];

        $this->assertSame(['Satker', 'UKI'], array_column($jalur, 'tempat'));
        $this->assertArrayNotHasKey('hasil', $jalur[1]);
    }
}
