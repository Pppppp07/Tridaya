<?php

namespace App\Http\Controllers;

use App\Enums\HasilTelaah;
use App\Enums\JenisReferensi;
use App\Enums\SumberLaporan;
use App\Models\KategoriTemuan;
use App\Models\Referensi;
use App\Models\Satker;
use App\Support\Dasbor;
use App\Support\DasborKeadaan;
use App\Support\Tampil;
use App\Support\Terlihat;
use Illuminate\Http\Request;

/**
 * Ringkasan — dasbor pemantauan, padanan `DasborUji` prototipe (uji coba 23–24
 * Sep 2026, disetujui 25 Sep menggantikan Ringkasan lama). Kata mentor Hizkia:
 * Ringkasan lama "lebih ke laporan bukan dashboard monitoring yang simple,
 * visual aktif". Susunannya:
 *
 *   1  Filter sekali untuk seluruh halaman (jenis, tahun, satuan kerja, dan
 *      filter tambahan), bentuknya seperti slicer Power BI.
 *   2  Ringkasan utama: lima kartu, semuanya per TINDAK LANJUT SATUAN KERJA.
 *   3  Rincian: klik satu kartu untuk membuka bagian-bagian angkanya dan dua
 *      sampai tiga grafiknya. Tiap grafik juga filter.
 *   4  Tabel keseluruhan di halaman keduanya (Tahun → Satuan kerja); tiap
 *      angkanya pintasan ke rekomendasi di baliknya.
 *
 * Hitungannya di `App\Support\Dasbor` (padanan `dasbor-uji.js`), keadaannya di
 * `App\Support\DasborKeadaan` (padanan `filterDasbor`).
 */
class RingkasanController extends Controller
{
    /** Muatan yang dibutuhkan seluruh hitungan halaman ini. */
    private const MUAT = [
        'temuan.laporan', 'temuan.kategori', 'temuan.kategoriIntern', 'sifat',
        'sasaran.satker', 'sasaran.tindakan.bentuk', 'sasaran.riwayatStatus',
    ];

    /* Satu satuan kerja, enam teratas; sisanya lewat "Semua (N)". */
    public const TERATAS = 6;

    /** Kunci sesi keadaan terakhir — filter dibawa saat dibuka lagi lewat menu. */
    private const SESI = 'ringkasan.keadaan';

    public function __invoke(Request $req)
    {
        $terlihat = Terlihat::untuk();
        /* Dimuat lewat filter hak akses, bukan Rekomendasi::all(): angka yang
           bocor jauh lebih sulit disadari daripada halaman yang bocor. */
        $rek = $terlihat->rekomendasi()->with(self::MUAT)->orderBy('rekomendasis.id')->get()
            ->map(fn ($r) => $terlihat->pangkasRekomendasi($r));
        $items = Dasbor::butir($rek);

        /* Isi tiap filter dibaca dari data dan data master SAAT INI. */
        $kelompok = Dasbor::kelompokSatker(Satker::orderBy('id')->get(), $items);
        $opsiTambahan = $this->opsiTambahan($items);
        $tahunSemua = Dasbor::daftarTahun($items);
        $sah = [
            'tahun'  => $tahunSemua,
            'satker' => array_column($kelompok['semua'], 'k'),
            'lain'   => array_map(fn ($o) => array_column($o, 'k'), $opsiTambahan),
        ];

        /* Dibuka lewat menu (tanpa isi alamat): filter dan kartu terakhir
           dibawa lagi, halamannya kembali ke kartu dan tabelnya tertutup —
           sama dengan prototipe, yang menyimpan filternya di App selama
           sesi. Alamatnya ikut ditulis, jadi yang terlihat selalu bisa disalin. */
        if ($req->query() === [] && is_array($simpan = session(self::SESI))) {
            $k = DasborKeadaan::dari(Request::create('/', 'GET', $simpan));
            $k = array_merge($k, ['hal' => 'dasbor', 'buka' => [], 'periode' => '12', 'tabel' => [], 'semua' => [], 'pintas' => '']);
            $k['f'] = Dasbor::bersihkan($k['f'], $sah);
            if (DasborKeadaan::keQuery($k)) {
                return redirect(DasborKeadaan::alamat($k));
            }
        }

        $k = DasborKeadaan::dari($req);
        /* Filter yang BERLAKU: pilihan yang sudah tidak ada di daftarnya dibuang. */
        $k['f'] = Dasbor::bersihkan($k['f'], $sah);

        /* Rekomendasi dibuka dari dasbor (tabel "Sisa nilai terbesar", daftar
           pintasan): tombol Kembali di rinciannya mendarat di sini lagi. */
        /* `lihat` = "rekomendasi[:satuan kerja[:tindakan]]" (26 Sep): kalau baris
           yang diwakilinya milik SATU satuan kerja, tiket dan barisnya dibuka
           lalu disorot di rincian — padanan `tujuBaris` prototipe. */
        if ($req->filled('lihat') && preg_match('/^(\d+)(?::(\d+))?(?::(\d+))?$/', (string) $req->query('lihat'), $m)) {
            return redirect($this->keRincian((int) $m[1], $k, isset($m[2]) ? (int) $m[2] : null,
                isset($m[3]) ? (int) $m[3] : null));
        }

        if ($req->filled('ubah')) {
            [$ubah, $jangkar] = array_pad(explode('@', (string) $req->query('ubah'), 2), 2, '');
            $k = DasborKeadaan::terapkan($k, $ubah, fn ($j) => SumberLaporan::from($j)->melewatiSiptl());
            $k['f'] = Dasbor::bersihkan($k['f'], $sah);
            /* Angka yang di baliknya cuma satu rekomendasi langsung membuka
               rinciannya; lebih dari satu membuka daftarnya di dekat sel. */
            if (str_starts_with($ubah, 'pintas:')) {
                $daftar = $this->isiPintas(Dasbor::terapkanFilter($items, $k['f']), $k['pintas']);
                if (count($daftar) === 1) {
                    $k['pintas'] = '';
                    [$sid, $tid] = self::tujuBaris($daftar[0]['baris']);

                    return redirect($this->keRincian($daftar[0]['x']['rek']->id, $k, $sid, $tid));
                }
                if (! $daftar) {
                    $k['pintas'] = '';
                }
            }

            /* Disimpan sebelum dialihkan (29 Sep): "Hapus filter" tanpa kartu
               terbuka berakhir di alamat kosong, dan tanpa ini alamat kosong
               itu dibaca sebagai "dibuka lewat menu" — filter lama dipasang
               lagi dari sesi. */
            session([self::SESI => DasborKeadaan::keQuery($k)]);

            return redirect(DasborKeadaan::alamat($k, $jangkar));
        }

        session([self::SESI => DasborKeadaan::keQuery($k)]);

        return view('ringkasan.index', $this->susun($k, $items, $kelompok, $opsiTambahan, $tahunSemua));
    }

    /** Alamat rincian rekomendasi yang Kembali-nya mendarat di dasbor ini. */
    private function keRincian(int $id, array $k, ?int $satker = null, ?int $tindakan = null): string
    {
        $k['pintas'] = '';

        return route('rekomendasi.show', array_filter(['rekomendasi' => $id, 'dari' => 'ringkasan',
            'ring' => http_build_query(DasborKeadaan::keQuery($k)),
            'sorot' => $satker ? 'r-tindaklanjut' : null, 'satker' => $satker, 'tindakan' => $tindakan]));
    }

    /**
     * Satuan kerja (dan tindak lanjutnya, kalau cuma satu) yang diwakili
     * sekumpulan baris — atau [null, null] kalau barisnya milik beberapa
     * satuan kerja.
     *
     * @return array{0: ?int, 1: ?int}
     */
    public static function tujuBaris($baris): array
    {
        $satker = collect($baris)->pluck('satker_id')->filter()->unique()->values();
        if ($satker->count() !== 1) {
            return [null, null];
        }
        $tindakan = collect($baris)->pluck('tindakan_id')->filter()->unique()->values();

        return [$satker->first(), $tindakan->count() === 1 ? $tindakan->first() : null];
    }

    /** Nilai tombol `lihat`: "rekomendasi[:satuan kerja[:tindakan]]". */
    public static function nilaiLihat(int $rekId, $baris): string
    {
        [$sid, $tid] = self::tujuBaris($baris);

        return implode(':', array_filter([$rekId, $sid, $sid ? $tid : null]));
    }

    /** `tahun|satker|kolom` → isi sel tabel keseluruhan. */
    private function isiPintas(array $T, string $pintas): array
    {
        if ($pintas === '') {
            return [];
        }
        [$tahun, $satker, $kolom] = explode('|', $pintas);

        return Dasbor::isiSel($T, $tahun !== '' ? $tahun : null, $satker !== '' ? (int) $satker : null, $kolom);
    }

    /** Pilihan filter tambahan dari data master masing-masing. */
    private function opsiTambahan(array $items): array
    {
        $ref = fn (JenisReferensi $j) => Referensi::where('jenis', $j->value)->orderBy('urutan')->orderBy('id')->get();
        $urutSumber = array_flip(['LHP', 'LHA']);
        $master = [
            /* Tanpa titik warna sejak 27 Sep — kategori tidak lagi berwarna. */
            'intern' => $ref(JenisReferensi::KATEGORI_INTERN)->map(fn ($x) => ['nama' => $x->nama,
                'aktif' => (bool) $x->aktif])->all(),
            'kategori' => KategoriTemuan::orderBy('urutan')->orderBy('id')->get()
                ->sortBy(fn ($x) => [$urutSumber[$x->sumber->value] ?? 9, $x->urutan, $x->id])->values()
                ->map(fn ($x) => ['nama' => $x->nama, 'aktif' => (bool) $x->aktif, 'grup' => $x->sumber->value])->all(),
            'sifat' => $ref(JenisReferensi::SIFAT_REKOM)->map(fn ($x) => ['nama' => $x->nama, 'aktif' => (bool) $x->aktif])->all(),
            'bentuk' => $ref(JenisReferensi::BENTUK_TL)->map(fn ($x) => ['nama' => $x->nama, 'aktif' => true])->all(),
        ];

        return array_combine(Dasbor::URUT_LAIN, array_map(
            fn ($d) => Dasbor::opsiLain($d, $master[$d], $items, $d === 'bentuk'), Dasbor::URUT_LAIN));
    }

    /**
     * Seluruh angka halaman — padanan memo `d` dan turunannya di `DasborUji`.
     */
    private function susun(array $k, array $items, array $kelompok, array $opsiTambahan, array $tahunSemua): array
    {
        $f = $k['f'];
        $hariIni = now()->toDateString();
        $K = Dasbor::terapkanFilter($items, $f, ['hasil']);
        $T = Dasbor::terapkanFilter($items, $f);

        /* Jumlah tiap pilihan dihitung dengan filter lain yang berlaku,
           tanpa filternya sendiri. */
        $hitung = [
            'jenis'  => Dasbor::hitungPilihan('jenis', Dasbor::terapkanFilter($items, $f, ['jenis'])),
            'tahun'  => Dasbor::hitungPilihan('tahun', Dasbor::terapkanFilter($items, $f, ['tahun'])),
            'satker' => Dasbor::hitungPilihan('satker', Dasbor::terapkanFilter($items, $f, ['satker'])),
            'lain'   => [],
        ];
        foreach (array_keys($f['lain']) as $d) {
            $hitung['lain'][$d] = Dasbor::hitungPilihan($d, Dasbor::terapkanFilter($items, $f, ["lain:$d"]));
        }

        $bulan = Dasbor::bulanTerakhir($hariIni, 12);
        $tahunTren = Dasbor::tahunKejadian($items);
        /* Tahun yang tidak ada lagi di data jatuh ke 12 bulan. */
        $periode = $k['periode'] !== '12' && in_array($k['periode'], $tahunTren, true) ? $k['periode'] : '12';
        $bulanTren = $periode === '12' ? $bulan : Dasbor::bulanTahun((int) $periode, $hariIni);

        $t = Dasbor::hitungTindak($K);
        $d = [
            'K'        => $K,
            'T'        => $T,
            'hitung'   => $hitung,
            'tindak'   => $t,
            'tunggu'   => Dasbor::perTunggu(Dasbor::terapkanFilter($items, $f, ['satker', 'hasil'])),
            'tahun'    => Dasbor::perTahun(Dasbor::terapkanFilter($items, $f, ['tahun', 'hasil'])),
            'satker'   => Dasbor::perSatker(Dasbor::terapkanFilter($items, $f, ['satker', 'hasil'])),
            'meja'     => Dasbor::perMeja(Dasbor::terapkanFilter($items, $f, ['meja'])),
            'banding'  => Dasbor::hitungBanding(Dasbor::terapkanFilter($items, $f, ['hasil', 'bpk'])),
            'kategori' => Dasbor::perKategori(Dasbor::terapkanFilter($items, $f, ['lain:intern', 'hasil']), $opsiTambahan['intern']),
            'tren'     => Dasbor::tren($T, $bulanTren),
            'tumpukan' => Dasbor::tumpukan($K, $bulan),
            'perlu'    => Dasbor::perluPerhatian($T, 5),
        ];

        $jenisUtama = $f['jenis'] !== 'semua' ? $f['jenis'] : ($t['lapLhp'] >= $t['lap'] - $t['lapLhp'] ? 'LHP' : 'LHA');
        $sumber = SumberLaporan::from($jenisUtama);
        $tp = $d['tumpukan'];

        $namaSatker = function ($id) use ($kelompok) {
            foreach ($kelompok['semua'] as $o) {
                if ($o['k'] === $id) {
                    return $o['nama'];
                }
            }

            return 'Tidak diisi';
        };

        $tindakSemua = array_sum(array_map(fn ($x) => $x['semua']->count(), $items));

        return [
            'k'            => $k,
            'f'            => $f,
            'd'            => $d,
            't'            => $t,
            'kataM'        => HasilTelaah::M->nama($sumber),
            'kataBM'       => HasilTelaah::BM->nama($sumber),
            'kelompok'     => $kelompok,
            'namaSatker'   => $namaSatker,
            'opsiJenis'    => array_map(fn ($j) => ['k' => $j, 'nama' => $j,
                'lengkap' => $j.' · '.SumberLaporan::from($j)->nama()], ['LHP', 'LHA']),
            'opsiTahun'    => array_map(fn ($th) => ['k' => $th, 'nama' => $th], array_reverse($tahunSemua)),
            'opsiTambahan' => $opsiTambahan,
            'tahunSemua'   => $tahunSemua,
            'bulan'        => $bulan,
            'bulanTren'    => $bulanTren,
            'tahunTren'    => $tahunTren,
            'periode'      => $periode,
            'tp'           => $tp,
            'gerak'        => $tp[count($tp) - 1] - $tp[count($tp) - 2],
            'bulanLalu'    => Dasbor::NAMA_BULAN[$bulan[count($bulan) - 2]['bl']],
            'jmlTindakT'   => array_sum(array_map(fn ($x) => $x['baris']->count(), $T)),
            'tindakSemua'  => $tindakSemua,
            'aktif'        => $this->aktif($f, $namaSatker),
            'tk'           => $k['hal'] === 'tabel' && $K ? $this->tabelKeseluruhan($T, $kelompok) : null,
            'pintas'       => $k['pintas'] !== '' ? $this->pintas($T, $k['pintas'], $namaSatker) : null,
            'hariIni'      => Tampil::tgl($hariIni),
        ];
    }

    /**
     * Seluruh filter yang sedang berlaku, satu keping per jenis filter.
     * Lebih dari dua pilihan cukup disebut jumlahnya; daftar lengkapnya di
     * keterangan kepingnya.
     */
    private function aktif(array $f, callable $namaSatker): array
    {
        $namaLain = fn ($v) => $v !== '' ? $v : 'Tidak diisi';
        $tahun = $f['tahun'];
        sort($tahun, SORT_STRING);
        $pendek = fn ($id) => Satker::find($id)?->namaPendek() ?? 'Tidak diisi';
        $a = [];
        if ($f['jenis'] !== 'semua') {
            $a[] = ['k' => 'jenis', 'nama' => 'Hanya '.$f['jenis'], 'lengkap' => 'Jenis laporan: '.$f['jenis']];
        }
        if ($tahun) {
            $a[] = ['k' => 'tahun', 'nama' => count($tahun) <= 2 ? 'Tahun '.implode(', ', $tahun) : count($tahun).' tahun',
                'lengkap' => 'Tahun '.implode(', ', $tahun)];
        }
        if ($f['satker']) {
            $a[] = ['k' => 'satker',
                'nama' => count($f['satker']) <= 2 ? implode(', ', array_map($namaSatker, $f['satker'])) : count($f['satker']).' satuan kerja',
                'lengkap' => implode(', ', array_map($pendek, $f['satker']))];
        }
        foreach ($f['lain'] as $d => $v) {
            if (! $v) {
                continue;
            }
            $D = Dasbor::DIM_LAIN[$d];
            $a[] = ['k' => 'lain:'.$d,
                'nama' => count($v) === 1
                    ? (isset($D['sebut']) ? $D['sebut'].': '.$namaLain($v[0]) : $namaLain($v[0]))
                    : $D['nama'].': '.count($v),
                'lengkap' => $D['nama'].': '.implode(', ', array_map($namaLain, $v))];
        }
        if ($f['hasil'] !== '') {
            $a[] = ['k' => 'hasil', 'hasil' => $f['hasil']];
        }
        if ($f['bpk']) {
            $a[] = ['k' => 'bpk', 'nama' => 'BPK: '.implode(', ', $f['bpk']),
                'lengkap' => 'Status BPK: '.implode(', ', array_map(
                    fn ($x) => $x.' '.\App\Enums\StatusTindakLanjut::from($x)->pendek(), $f['bpk']))];
        }
        if ($f['meja']) {
            $nama = array_map(fn ($m) => Dasbor::NAMA_MEJA[$m], $f['meja']);
            $a[] = ['k' => 'meja', 'nama' => count($nama) <= 2 ? 'Di meja '.implode(', ', $nama) : count($nama).' meja',
                'lengkap' => 'Di meja '.implode(', ', $nama)];
        }

        return $a;
    }

    /**
     * Tabel keseluruhan: satu tabel bertingkat Tahun → Satuan kerja, memakai
     * SEMUA filter yang berlaku. Satuan kerja tiap tahun dihitung dengan
     * `perSatker` yang sama, dijalankan per tahun; kolom Rekomendasi
     * menghitung rekomendasi unik.
     */
    private function tabelKeseluruhan(array $T, array $kelompok): array
    {
        $urutMaster = [];
        foreach ($kelompok['semua'] as $i => $o) {
            $urutMaster[$o['k']] = $i;
        }
        $kunci = ['rek', 'tugas', 'M', 'BM', 'lhp', 'SS', 'BS', 'BT', 'TD', 'nilai', 'sisaBpk', 'sisa'];
        $jumlah = fn (array $arr) => array_combine($kunci, array_map(
            fn ($kk) => array_sum(array_column($arr, $kk)), $kunci));
        $rekUnik = fn (array $arr) => count(array_unique(array_map(fn ($x) => $x['rek']->id,
            array_filter($arr, fn ($x) => $x['baris']->isNotEmpty()))));

        $tahunAda = array_values(array_unique(array_map(fn ($x) => Dasbor::tahun($x), $T)));
        $kelompokTk = [];
        foreach ($tahunAda as $y) {
            $Ty = array_values(array_filter($T, fn ($x) => Dasbor::tahun($x) === $y));
            $baris = array_map(fn ($o) => [
                'k' => $o['k'], 'nama' => $o['pendek'], 'rek' => $o['nRek'], 'tugas' => $o['M'] + $o['BM'],
                'urutNama' => $urutMaster[$o['k']] ?? 9999,
                'M' => $o['M'], 'BM' => $o['BM'], 'lhp' => $o['lhp'], 'SS' => $o['SS'], 'BS' => $o['BS'],
                'BT' => $o['BT'], 'TD' => $o['TD'], 'nilai' => $o['nilai'], 'sisaBpk' => $o['sisaBpk'], 'sisa' => $o['sisa'],
            ], Dasbor::perSatker($Ty));
            if ($baris) {
                $kelompokTk[] = ['k' => $y, 'baris' => $baris, 'jumlah' => array_merge($jumlah($baris), ['rek' => $rekUnik($Ty)])];
            }
        }
        $semuaBaris = array_merge(...array_map(fn ($g) => $g['baris'], $kelompokTk ?: [['baris' => []]]));

        return [
            'kelompok' => $kelompokTk,
            'total'    => array_merge($jumlah($semuaBaris), ['rek' => $rekUnik($T)]),
            'adaTD'    => collect($semuaBaris)->contains(fn ($o) => $o['TD'] > 0),
        ];
    }

    /** Daftar rekomendasi di balik satu angka tabel keseluruhan. */
    private function pintas(array $T, string $pintas, callable $namaSatker): ?array
    {
        [$tahun, $satker, $kolom] = explode('|', $pintas);
        $daftar = $this->isiPintas($T, $pintas);
        if (count($daftar) < 2) {
            return null;
        }

        return [
            'kunci'      => $pintas,
            'tahun'      => $tahun,
            'satker'     => $satker !== '' ? (int) $satker : null,
            'namaSatker' => $satker !== '' ? (\App\Models\Satker::find((int) $satker)?->namaPendek() ?? 'Tidak diisi') : null,
            'kolom'      => $kolom,
            'daftar'     => $daftar,
        ];
    }
}
