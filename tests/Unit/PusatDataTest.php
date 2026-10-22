<?php

namespace Tests\Unit;

use App\Models\Satker;
use App\Models\User;
use App\Support\BedaLog;
use App\Support\PusatData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

/** Aturan hitung Data master Admin DTI (kasus dasar dari Prototipe/alat/uji-pusat-data.mjs). */
class PusatDataTest extends TestCase
{
    private Carbon $hari;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hari = Carbon::parse('2026-08-17 12:00:00');
    }

    private function akun(string $id, string $peran, bool $aktif, string $nama, string $nip, string $email, ?string $masuk = null, ?string $unit = null): User
    {
        $u = new User(['name' => $nama, 'nip' => $nip, 'email' => $email, 'peran' => $peran, 'aktif' => $aktif]);
        $u->id = (int) preg_replace('/\D/', '', $id) ?: crc32($id);
        $u->terakhir_masuk_pada = $masuk ? Carbon::parse($masuk) : null;
        $u->setRelation('satker', $unit ? new Satker(['nama' => $unit]) : null);

        return $u;
    }

    /** @return Collection<int, User> */
    private function semuaAkun(): Collection
    {
        return collect([
            $this->akun('a1', 'setba', true, 'Sri Wahyuni', '1', 'setba@contoh.test', '2026-08-17 08:00:00'),
            $this->akun('a2', 'uki', true, 'Budi Santoso', '2', 'uki@contoh.test', '2026-06-01 09:00:00'),
            $this->akun('a3', 'admin', true, 'Ratna Dewi', '3', 'admin@contoh.test', '2026-08-12 10:00:00'),
            $this->akun('a4', 'dti', false, 'Agus Pratama', '4', 'dti@contoh.test'),
            $this->akun('p1', 'satker', true, 'Citra Lestari', '5', 'medan@contoh.test', '2026-08-10 10:00:00', 'Balai Wil. I Medan'),
            $this->akun('p2', 'satker', true, 'Dodi Hermawan', '6', 'poli@contoh.test', null, 'Politeknik PU'),
        ]);
    }

    private function nama(Collection $c): array
    {
        return $c->pluck('nip')->map(fn ($n) => (int) $n)->all();
    }

    public function test_selisih_hari_kalender(): void
    {
        $this->assertSame(7, PusatData::selisihHari('2026-08-10', $this->hari));
        $this->assertSame(1, PusatData::selisihHari('2026-08-10 23:59:59', '2026-08-11 00:00:01'));
        $this->assertSame(0, PusatData::selisihHari($this->hari, $this->hari));
    }

    public function test_terakhir_masuk_baru_sepi_belum(): void
    {
        $h = $this->hari;
        $this->assertTrue(PusatData::cocokMasuk('baru', Carbon::parse('2026-08-10 10:00:00'), $h));   // tepat 7 hari
        $this->assertFalse(PusatData::cocokMasuk('baru', Carbon::parse('2026-08-09 10:00:00'), $h));
        $this->assertFalse(PusatData::cocokMasuk('sepi', Carbon::parse('2026-07-18 10:00:00'), $h));  // 30 hari — belum lewat
        $this->assertTrue(PusatData::cocokMasuk('sepi', Carbon::parse('2026-07-17 10:00:00'), $h));   // 31 hari
        $this->assertTrue(PusatData::cocokMasuk('sepi', null, $h));                                    // belum pernah termasuk sepi
        $this->assertTrue(PusatData::cocokMasuk('belum', null, $h));
        $this->assertFalse(PusatData::cocokMasuk('belum', Carbon::parse('2026-08-01'), $h));
        $this->assertTrue(PusatData::cocokMasuk('', null, $h));
        $this->assertSame(7, PusatData::HARI_BARU);
        $this->assertSame(30, PusatData::HARI_SEPI);
    }

    public function test_chip_keadaan(): void
    {
        $a = $this->semuaAkun();
        $h = fn (array $f) => $this->nama(PusatData::saringAkun($a, $f, $this->hari));

        $this->assertSame([1, 2, 3, 4, 5, 6], $h([]));
        $this->assertSame([1, 2, 3], $h(['filter' => 'pusat']));
        $this->assertSame([5, 6], $h(['filter' => 'pj']));
        $this->assertSame([4], $h(['filter' => 'nonaktif']));
    }

    public function test_peran_masuk_dan_kata_kunci_digabung(): void
    {
        $a = $this->semuaAkun();
        $h = fn (array $f) => $this->nama(PusatData::saringAkun($a, $f, $this->hari));

        $this->assertSame([5, 6], $h(['peran' => 'satker']));
        $this->assertSame([6], $h(['peran' => 'satker', 'masuk' => 'belum']));
        $this->assertSame([2, 4, 6], $h(['masuk' => 'sepi']));
        $this->assertSame([5], $h(['q' => 'medan']));               // lewat nama unit dan email
        $this->assertSame([3], $h(['q' => 'admin dti']));           // lewat sebutan peran
        $this->assertSame([2], $h(['filter' => 'pusat', 'masuk' => 'sepi']));
    }

    public function test_urutan_nama_peran_masuk(): void
    {
        $a = $this->semuaAkun();
        $u = fn (string $urut, string $arah) => $this->nama(PusatData::urutkanAkun($a, $urut, $arah));

        $this->assertSame([4, 2, 5, 6, 3, 1], $u('nama', 'naik'));
        $this->assertSame([1, 3, 6, 5, 2, 4], $u('nama', 'turun'));
        $this->assertSame([1, 2, 4, 3, 5, 6], $u('peran', 'naik'));
        // masuk naik: yang belum pernah (4, 6) paling atas, sisanya dari yang terlama
        $this->assertSame([4, 6, 2, 5, 3, 1], $u('masuk', 'naik'));
        $this->assertSame([1, 3, 5, 2, 4, 6], $u('masuk', 'turun'));
        $this->assertSame($a, PusatData::urutkanAkun($a, '', 'naik'));   // tanpa urutan: apa adanya
    }

    public function test_hitung_akun(): void
    {
        $this->assertSame(['semua' => 6, 'aktif' => 5, 'pusat' => 3, 'pj' => 2, 'nonaktif' => 1],
            PusatData::hitungAkun($this->semuaAkun()));
    }

    /** @return Collection<int, Satker> */
    private function semuaUnit(): Collection
    {
        $unit = function (int $id, string $nama, string $pendek, bool $aktif, ?User $pj) {
            $s = new Satker(['nama' => $nama, 'nama_pendek' => $pendek, 'aktif' => $aktif]);
            $s->id = $id;
            $s->setRelation('penanggungJawab', $pj);

            return $s;
        };

        return collect([
            $unit(1, 'Balai Pengembangan Kompetensi Wilayah I Medan', 'Balai Wil. I Medan', true,
                $this->akun('p1', 'satker', true, 'Citra', '5', 'c@x')),
            $unit(2, 'Politeknik PU', 'Politeknik PU', true, null),
            $unit(3, 'Pusat Lama', 'Pusat Lama', false, null),
        ]);
    }

    public function test_filter_unit(): void
    {
        $u = $this->semuaUnit();
        $h = fn (array $f) => PusatData::saringUnit($u, $f)->pluck('id')->all();

        $this->assertSame([1, 2, 3], $h([]));
        $this->assertSame([1, 2], $h(['filter' => 'aktif']));
        $this->assertSame([3], $h(['filter' => 'nonaktif']));
        $this->assertSame([2], $h(['filter' => 'tanpa-pj']));   // nonaktif tanpa PJ tidak dihitung
        $this->assertSame([1], $h(['q' => 'citra']));
    }

    public function test_urut_unit_menurut_rekomendasi_dan_nama(): void
    {
        $u = $this->semuaUnit();
        $pakai = [2 => ['rek' => 9], 1 => ['rek' => 3]];
        $h = fn (array $f) => PusatData::saringUnit($u, $f, $pakai)->pluck('id')->all();

        $this->assertSame([2, 1, 3], $h(['urut' => 'rekomendasi', 'arah' => 'turun']));
        $this->assertSame([3, 1, 2], $h(['urut' => 'rekomendasi', 'arah' => 'naik']));
        $this->assertSame([1, 2, 3], $h(['urut' => 'nama', 'arah' => 'naik']));   // Balai, Politeknik, Pusat
        $this->assertSame([3, 2, 1], $h(['urut' => 'nama', 'arah' => 'turun']));
    }

    public function test_data_sehat_tanpa_butir_dan_butir_bertingkat_dengan_kalimat_tetap(): void
    {
        $sehat = $this->semuaAkun()->filter(fn ($a) => $a->aktif && $a->peran->value !== 'satker')->values();
        foreach ([['a5', 'setba', 7], ['a6', 'uki', 8], ['a7', 'inspektorat', 9], ['a8', 'inspektorat', 10], ['a9', 'admin', 11]] as [$id, $peran, $nip]) {
            $sehat->push($this->akun($id, $peran, true, "X$nip", (string) $nip, "x$nip@x", '2026-08-16 00:00:00'));
        }
        $sehat = $sehat->map(function ($a) {
            $a->terakhir_masuk_pada = Carbon::parse('2026-08-16 00:00:00');

            return $a;
        });
        $unitSehat = collect([tap(new Satker(['aktif' => true]), fn ($s) => $s->setRelation('penanggungJawab', $this->akun('p9', 'satker', true, 'Z', '99', 'z@x')))]);
        $this->assertSame([], PusatData::periksaKesehatan($unitSehat, $sehat, $this->hari));

        $hasil = PusatData::periksaKesehatan($this->semuaUnit(), $this->semuaAkun(), $this->hari, [
            ['tab' => 'temuan', 'saring' => ['segmen' => 'LHP'], 'nama' => 'kategori temuan LHP', 'n' => 2],
            ['tab' => 'intern', 'saring' => [], 'nama' => 'kategori internal', 'n' => 1],
        ]);
        $this->assertSame(['unit-tanpa-pj', 'peran-kosong-inspektorat', 'peran-tunggal', 'pj-belum-masuk', 'pusat-sepi', 'kategori-nganggur'],
            array_column($hasil, 'id'));
        $this->assertSame(['awas', 'awas', 'saran', 'saran', 'saran', 'saran'], array_column($hasil, 'tingkat'));
        $per = array_column($hasil, null, 'id');

        $this->assertSame('1 satuan kerja aktif belum punya penanggung jawab', $per['unit-tanpa-pj']['judul']);
        $this->assertSame('Belum ada akun penanggung jawab aktif untuk satuan kerja tersebut. Tetapkan penanggung jawab agar akses dan pemberitahuannya terhubung.', $per['unit-tanpa-pj']['ket']);
        $this->assertSame(['tab' => 'unit', 'saring' => ['filter' => 'tanpa-pj']], $per['unit-tanpa-pj']['tuju']);
        $this->assertSame('Belum ada akun Inspektorat yang aktif', $per['peran-kosong-inspektorat']['judul']);
        $this->assertSame('Tidak ada akun aktif yang berwenang memverifikasi berkas sebagai Inspektorat.', $per['peran-kosong-inspektorat']['ket']);
        $this->assertSame('Hanya satu akun aktif untuk Setba, UKI, dan Admin DTI', $per['peran-tunggal']['judul']);
        $this->assertSame('Peran tersebut sudah memiliki pemegang aktif. Tinjau kebutuhan petugas tambahan sesuai penetapan organisasi.', $per['peran-tunggal']['ket']);
        $this->assertSame(['tab' => 'pengguna', 'saring' => ['filter' => 'pusat']], $per['peran-tunggal']['tuju']);
        $this->assertSame('1 penanggung jawab satuan kerja belum pernah masuk', $per['pj-belum-masuk']['judul']);    // p2
        $this->assertSame('Akun belum memiliki riwayat masuk yang tercatat. Status tindak lanjut satuan kerja tetap dilihat pada rekomendasinya.', $per['pj-belum-masuk']['ket']);
        $this->assertSame('1 akun petugas pusat tidak masuk 30 hari terakhir', $per['pusat-sepi']['judul']); // a2 (Juni)
        $this->assertSame('3 kategori dan label aktif belum dipakai', $per['kategori-nganggur']['judul']);
        $this->assertSame('2 kategori temuan LHP, 1 kategori internal. Tinjau kebutuhan kategori sebelum menonaktifkan atau menggabungkannya.', $per['kategori-nganggur']['ket']);
        $this->assertSame(['tab' => 'temuan', 'saring' => ['segmen' => 'LHP']], $per['kategori-nganggur']['tuju']);

        /* Tanpa `nganggur`, hanya yang awas — untuk penanda di deret tab. */
        $awas = PusatData::periksaKesehatan($this->semuaUnit(), $this->semuaAkun(), $this->hari, null);
        $this->assertSame(['unit-tanpa-pj', 'peran-kosong-inspektorat'], array_column($awas, 'id'));
    }

    public function test_satu_pemegang_peran_dan_belum_masuk_hanya_informasi_tinjauan(): void
    {
        $akun = collect();
        foreach (['setba', 'uki', 'inspektorat', 'admin'] as $i => $peran) {
            $akun->push($this->akun('a'.($i + 1), $peran, true, $peran, (string) ($i + 1), "$peran@x"));
        }
        $pj = $this->akun('p1', 'satker', true, 'PJ aktif', '5', 'pj@x');
        $akun->push($pj);
        $akun->push($this->akun('p2', 'satker', false, 'PJ lama', '6', 'lama@x'));
        $unit = collect([tap(new Satker(['aktif' => true]), fn ($s) => $s->setRelation('penanggungJawab', $pj))]);

        // Satu akun aktif sudah menutup kekosongan kewenangan. Tidak ada
        // riwayat masuk bukan dasar untuk menandai tindak lanjut bermasalah.
        $this->assertSame([], PusatData::periksaKesehatan($unit, $akun, $this->hari, null));
        $hasil = PusatData::periksaKesehatan($unit, $akun, $this->hari);
        $this->assertSame(['peran-tunggal', 'pj-belum-masuk', 'pusat-sepi'], array_column($hasil, 'id'));
        $this->assertSame(['saran', 'saran', 'saran'], array_column($hasil, 'tingkat'));
        $per = array_column($hasil, null, 'id');
        $this->assertSame(1, $per['pj-belum-masuk']['n']); // akun lama/nonaktif tidak ikut
        $this->assertSame(4, $per['pusat-sepi']['n']);
    }

    public function test_akun_nonaktif_tidak_menutup_kekosongan_peran_penting(): void
    {
        $akun = collect();
        foreach (['setba', 'uki', 'inspektorat', 'admin', 'dti', 'pimpinan'] as $i => $peran) {
            $akun->push($this->akun('a'.($i + 1), $peran, false, $peran, (string) ($i + 1), "$peran@x"));
        }

        $hasil = PusatData::periksaKesehatan(collect(), $akun, $this->hari, null);
        $this->assertSame(['peran-kosong-setba', 'peran-kosong-uki', 'peran-kosong-inspektorat', 'peran-kosong-admin'], array_column($hasil, 'id'));
        $this->assertSame(['awas', 'awas', 'awas', 'awas'], array_column($hasil, 'tingkat'));
    }

    public function test_kata_sambung(): void
    {
        $this->assertSame('Setba', PusatData::sambungKata(['Setba']));
        $this->assertSame('Setba dan UKI', PusatData::sambungKata(['Setba', 'UKI']));
        $this->assertSame('Setba, UKI, dan Admin DTI', PusatData::sambungKata(['Setba', 'UKI', 'Admin DTI']));
        $this->assertSame('A, B, C, dan D', PusatData::sambungKata(['A', 'B', 'C', 'D']));
    }

    public function test_nama_dan_nip_yang_ditempel(): void
    {
        $this->assertSame(['Kepatuhan', 'Lain-lain dua'], PusatData::uraiBaris("  Kepatuhan \n\nkepatuhan\r\nLain-lain  dua\n"));
        $this->assertSame([], PusatData::uraiBaris(''));
        $this->assertSame(['197201011996012001', '197201011996012002'],
            PusatData::uraiNip("197201011996012001\n19720101 199601 2 002, abc; 197201011996012001\n"));
        $this->assertSame([], PusatData::uraiNip("\t\n  "));
    }

    public function test_geser_di_antara_sesamanya_dan_berhenti_di_ujung(): void
    {
        $this->assertSame([3, 1, 2], PusatData::geser([1, 3, 2], 3, 'atas'));
        $this->assertNull(PusatData::geser([1, 3, 2], 1, 'atas'));    // sudah paling atas
        $this->assertNull(PusatData::geser([1, 3, 2], 2, 'bawah'));   // sudah paling bawah
        $this->assertSame([1, 2, 3], PusatData::geser([1, 3, 2], 3, 'bawah'));
        $this->assertNull(PusatData::geser([1, 3, 2], 9, 'atas'));    // tidak ada
    }

    public function test_beda_log_dibaca_sama_untuk_tampilan_dan_csv(): void
    {
        $rincian = ['sebelum' => ['aktif' => true, 'nama' => 'Lama'], 'sesudah' => ['aktif' => false, 'nama' => 'Baru', 'dipindah' => '15 temuan', 'tidak_dikenal' => 'x']];
        $this->assertSame('Status: aktif → nonaktif; Nama: Lama → Baru; Dipindahkan: 15 temuan', BedaLog::teks($rincian));
        $this->assertSame('', BedaLog::teks(null));
        $this->assertSame('Sesi diakhiri: 2 perangkat', BedaLog::teks(['sesudah' => ['sesi' => '2 perangkat']]));
        $this->assertSame('Urutan: 3 → 2', BedaLog::teks(['sebelum' => ['urutan' => 3], 'sesudah' => ['urutan' => 2]]));
    }
}
