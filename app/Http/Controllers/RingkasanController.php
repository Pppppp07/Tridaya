<?php

namespace App\Http\Controllers;

use App\Enums\HasilTelaah;
use App\Enums\JenisReferensi;
use App\Enums\StatusTindakLanjut;
use App\Enums\SumberLaporan;
use App\Models\KategoriTemuan;
use App\Models\Referensi;
use App\Models\Satker;
use App\Support\Dasbor;
use App\Support\DasborKeadaan;
use App\Support\Halaman;
use App\Support\Tampil;
use App\Support\Terlihat;
use Illuminate\Http\Request;

/**
 * Dashboard pemantauan: satu filter untuk seluruh halaman, empat kartu (semua per tindak lanjut
 * satuan kerja), klik kartu untuk rinciannya.
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
        /* Dimuat lewat filter hak akses, bukan Rekomendasi::all(): angka yang bocor lebih sulit
           disadari daripada halaman yang bocor. */
        $rek = $terlihat->rekomendasi()->with(self::MUAT)->orderBy('rekomendasis.id')->get()
            ->map(fn ($r) => $terlihat->pangkasRekomendasi($r));
        $utuh = Dasbor::butir($rek);
        /* Tab data (semua, sistem, historis) hanya ada bila ada data historis yang terlihat. */
        $adaHistoris = collect($utuh)->contains(fn ($x) => $x['semua']->contains(fn ($b) => $b->historis()));
        $tabData = fn (array $k) => $adaHistoris ? $k['data'] : 'semua';

        /* Isi tiap filter dibaca dari data tab yang dipilih dan data master SAAT INI. */
        $siapkan = function (string $data) use ($utuh, $terlihat) {
            $items = Dasbor::dalamData($utuh, $data);
            $kelompok = Dasbor::kelompokSatker($terlihat->satkers()->orderBy('id')->get(), $items);
            $opsiTambahan = $this->opsiTambahan($items);
            $tahunSemua = Dasbor::daftarTahun($items);
            $sah = [
                'tahun' => $tahunSemua,
                'satker' => array_column($kelompok['semua'], 'k'),
                'lain' => array_map(fn ($o) => array_column($o, 'k'), $opsiTambahan),
            ];

            return [$items, $kelompok, $opsiTambahan, $tahunSemua, $sah];
        };

        /* Dibuka lewat menu (tanpa isi alamat): filter dan kartu terakhir dibawa lagi dari sesi,
           dan alamatnya ikut ditulis. */
        if ($req->query() === [] && ! Halaman::bagian() && is_array($simpan = session(self::SESI))) {
            $k = DasborKeadaan::dari(Request::create('/', 'GET', $simpan));
            $k = array_merge($k, ['hal' => 'dasbor', 'buka' => [], 'periode' => '', 'tabel' => [], 'semua' => [], 'pintas' => '']);
            $k['data'] = $tabData($k);
            $k['f'] = Dasbor::bersihkan($k['f'], $siapkan($k['data'])[4]);
            if (DasborKeadaan::keQuery($k)) {
                return redirect(DasborKeadaan::alamat($k));
            }
        }

        $k = DasborKeadaan::dari($req);
        $k['data'] = $tabData($k);
        [$items, $kelompok, $opsiTambahan, $tahunSemua, $sah] = $siapkan($k['data']);
        /* Tanpa pilihan di alamat: LHP, kecuali akun yang hanya punya tindak lanjut LHA. */
        $jenisDiAlamat = in_array($req->query('jenis'), ['LHP', 'LHA'], true);
        if (! $jenisDiAlamat) {
            $k['f']['jenis'] = self::jenisAwal($items);
        }
        /* Filter yang BERLAKU: pilihan yang sudah tidak ada di daftarnya dibuang. */
        $k['f'] = Dasbor::bersihkan($k['f'], $sah);
        $k = self::tanpaSiptl($k);

        /* Rekomendasi dibuka dari dasbor: tombol Kembali di rinciannya mendarat di sini lagi. */
        /* `lihat` = "rekomendasi[:satuan kerja[:tindakan]]": baris milik satu satuan kerja
           dibuka dan disorot di rincian. */
        if ($req->filled('lihat') && preg_match('/^(\d+)(?::(\d+))?(?::(\d+))?$/', (string) $req->query('lihat'), $m)) {
            return redirect($this->keRincian((int) $m[1], $k, isset($m[2]) ? (int) $m[2] : null,
                isset($m[3]) ? (int) $m[3] : null));
        }

        if ($req->filled('ubah')) {
            [$ubah, $jangkar] = array_pad(explode('@', (string) $req->query('ubah'), 2), 2, '');
            $k = DasborKeadaan::terapkan($k, $ubah, fn ($j) => SumberLaporan::from($j)->melewatiSiptl());
            $k['data'] = $tabData($k);
            if (str_starts_with($ubah, 'data:')) {
                [$items, $kelompok, $opsiTambahan, $tahunSemua, $sah] = $siapkan($k['data']);
                if (! $jenisDiAlamat) {
                    $k['f']['jenis'] = self::jenisAwal($items);
                }
            }
            $k['f'] = Dasbor::bersihkan($k['f'], $sah);
            $k = self::tanpaSiptl($k);
            /* Satu rekomendasi → langsung rinciannya; lebih → daftarnya di dekat sel. */
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

            /* Disimpan sebelum dialihkan: tanpa ini, "Hapus filter" mendarat di alamat kosong
               yang dibaca sebagai "dibuka lewat menu". */
            session([self::SESI => DasborKeadaan::keQuery($k)]);

            if (! Halaman::bagian()) {
                return redirect(DasborKeadaan::alamat($k, $jangkar));
            }
        }

        session([self::SESI => DasborKeadaan::keQuery($k)]);

        /* Angka di tab data: tindak lanjut jenis laporan yang dipilih, sebelum filter lain. */
        $jumlahData = [];
        foreach ($adaHistoris ? array_keys(Dasbor::DATA) : [] as $d) {
            $jumlahData[$d] = collect(Dasbor::dalamData($utuh, $d))
                ->filter(fn ($x) => $x['lap']->sumber->value === $k['f']['jenis'])
                ->sum(fn ($x) => $x['semua']->count());
        }
        $data = $this->susun($k, $items, $kelompok, $opsiTambahan, $tahunSemua)
            + ['adaHistoris' => $adaHistoris, 'jumlahData' => $jumlahData];
        if (Halaman::bagian()) {
            return response()->view('ringkasan.index', $data)
                ->header('X-Simtlhp-Url', DasborKeadaan::alamat($k, $jangkar ?? ''));
        }

        return view('ringkasan.index', $data);
    }

    private static function jenisAwal(array $items): string
    {
        $ada = fn (string $j) => collect($items)->contains(fn ($x) => $x['lap']->sumber->value === $j);

        return ! $ada(Dasbor::JENIS_AWAL) && $ada('LHA') ? 'LHA' : Dasbor::JENIS_AWAL;
    }

    /** Jenis laporan yang dipilih ikut SIPTL? */
    private static function adaSiptl(array $f): bool
    {
        return SumberLaporan::from($f['jenis'])->melewatiSiptl();
    }

    /** LHA tidak diunggah ke SIPTL: kartu dan filter status BPK-nya tidak berlaku. */
    private static function tanpaSiptl(array $k): array
    {
        if (! self::adaSiptl($k['f'])) {
            $k['f']['bpk'] = [];
            if ($k['kartu'] === 'ss') {
                $k['kartu'] = '';
            }
        }

        return $k;
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
     * Satuan kerja (dan tindak lanjutnya bila hanya satu) yang diwakili sekumpulan baris; [null,
     * null] bila milik beberapa satuan kerja.
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

    /** Seluruh angka halaman. */
    private function susun(array $k, array $items, array $kelompok, array $opsiTambahan, array $tahunSemua): array
    {
        $f = $k['f'];
        $hariIni = now()->toDateString();
        $K = Dasbor::terapkanFilter($items, $f, ['hasil']);
        $T = Dasbor::terapkanFilter($items, $f);

        /* Jumlah tiap pilihan dihitung dengan filter lain, tanpa filternya sendiri. */
        $hitung = [
            'jenis' => Dasbor::hitungPilihan('jenis', Dasbor::terapkanFilter($items, $f, ['jenis'])),
            'tahun' => Dasbor::hitungPilihan('tahun', Dasbor::terapkanFilter($items, $f, ['tahun'])),
            'satker' => Dasbor::hitungPilihan('satker', Dasbor::terapkanFilter($items, $f, ['satker'])),
            'lain' => [],
        ];
        foreach (array_keys($f['lain']) as $d) {
            $hitung['lain'][$d] = Dasbor::hitungPilihan($d, Dasbor::terapkanFilter($items, $f, ["lain:$d"]));
        }

        $tahunTren = Dasbor::tahunKejadian(array_filter($items, fn ($x) => $x['lap']->sumber->value === $f['jenis']));
        /* Grafik per bulan memakai satu tahun kalender; bawaannya tahun terbaru di data. */
        $periode = in_array($k['periode'], $tahunTren, true) ? $k['periode'] : ($tahunTren[0] ?? substr($hariIni, 0, 4));
        $bulanTren = Dasbor::bulanTahun((int) $periode, $hariIni);

        $t = Dasbor::hitungTindak($K);
        $d = [
            'K' => $K,
            'T' => $T,
            'hitung' => $hitung,
            'tindak' => $t,
            'tunggu' => Dasbor::perProses(Dasbor::terapkanFilter($items, $f, ['satker', 'hasil'])),
            'tahun' => Dasbor::perTahun(Dasbor::terapkanFilter($items, $f, ['tahun', 'hasil'])),
            'satker' => Dasbor::perSatker(Dasbor::terapkanFilter($items, $f, ['satker', 'hasil'])),
            'meja' => Dasbor::perMeja(Dasbor::terapkanFilter($items, $f, ['meja'])),
            'banding' => Dasbor::hitungBanding(Dasbor::terapkanFilter($items, $f, ['hasil', 'bpk'])),
            'tren' => Dasbor::tren($T, $bulanTren),
            'perlu' => Dasbor::perluPerhatian($T, 5),
        ];
        /* Kotak rincian kartu = tombol filter: dihitung tanpa filternya sendiri, seperti keping filter
           utama, supaya pilihan lain tetap tampil dengan angkanya. */
        $sendiri = ['tunggu' => 'meja', 'ss' => 'bpk'][$k['kartu']] ?? null;
        $d['tindakKotak'] = $sendiri ? Dasbor::hitungTindak(Dasbor::terapkanFilter($items, $f, ['hasil', $sendiri])) : $t;

        /* LHP "memadai", LHA "sesuai". */
        $sumber = SumberLaporan::from($f['jenis']);

        $namaSatker = function ($id) use ($kelompok) {
            foreach ($kelompok['semua'] as $o) {
                if ($o['k'] === $id) {
                    return $o['nama'];
                }
            }

            return 'Tidak diisi';
        };

        /* Acuan "dari N": seluruh tindak lanjut jenis laporan yang dipilih. */
        $tindakSemua = array_sum(array_map(fn ($x) => $x['semua']->count(),
            array_filter($items, fn ($x) => $x['lap']->sumber->value === $f['jenis'])));

        return [
            'k' => $k,
            'f' => $f,
            'd' => $d,
            't' => $t,
            'kataM' => HasilTelaah::M->nama($sumber),
            'kataBM' => HasilTelaah::BM->nama($sumber),
            'adaSiptl' => self::adaSiptl($f),
            'kelompok' => $kelompok,
            'namaSatker' => $namaSatker,
            'opsiJenis' => array_map(fn ($j) => ['k' => $j, 'nama' => $j,
                'lengkap' => $j.' · '.SumberLaporan::from($j)->nama()], ['LHP', 'LHA']),
            'opsiTahun' => array_map(fn ($th) => ['k' => $th, 'nama' => $th], array_reverse($tahunSemua)),
            'opsiTambahan' => $opsiTambahan,
            'tahunSemua' => $tahunSemua,
            'bulanTren' => $bulanTren,
            'tahunTren' => $tahunTren,
            'periode' => $periode,
            'jmlTindakT' => array_sum(array_map(fn ($x) => $x['baris']->count(), $T)),
            'tindakSemua' => $tindakSemua,
            'aktif' => $this->aktif($f, $namaSatker, array_column($kelompok['semua'], 'pendek', 'k')),
            'tk' => $k['hal'] === 'tabel' && $K ? $this->tabelKeseluruhan($T, $kelompok) : null,
            'pintas' => $k['pintas'] !== '' ? $this->pintas($T, $k['pintas'], $namaSatker) : null,
            'hariIni' => Tampil::tgl($hariIni),
        ];
    }

    /**
     * Filter yang berlaku, satu keping per jenis; lebih dari dua pilihan cukup disebut jumlahnya.
     */
    private function aktif(array $f, callable $namaSatker, array $pendekSatker): array
    {
        $namaLain = fn ($v) => $v !== '' ? $v : 'Tidak diisi';
        $tahun = $f['tahun'];
        sort($tahun, SORT_STRING);
        $pendek = fn ($id) => $pendekSatker[$id] ?? 'Tidak diisi';
        $a = [];
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
            /* "Belum diunggah" bukan status BPK: disebut terpisah. */
            $st = array_values(array_diff($f['bpk'], ['belumUnggah']));
            $belum = in_array('belumUnggah', $f['bpk'], true);
            $a[] = ['k' => 'bpk',
                'nama' => implode(' · ', array_filter([$st ? 'BPK: '.implode(', ', $st) : '', $belum ? 'Belum diunggah' : ''])),
                'lengkap' => implode(' · ', array_filter([
                    $st ? 'Status BPK: '.implode(', ', array_map(fn ($x) => $x.' '.StatusTindakLanjut::from($x)->pendek(), $st)) : '',
                    $belum ? 'Belum diunggah ke SIPTL' : '']))];
        }
        if ($f['meja']) {
            $nama = array_map(fn ($m) => Dasbor::NAMA_MEJA[$m], $f['meja']);
            $a[] = ['k' => 'meja', 'nama' => count($nama) <= 2 ? 'Di meja '.implode(', ', $nama) : count($nama).' meja',
                'lengkap' => 'Di meja '.implode(', ', $nama)];
        }

        return $a;
    }

    /**
     * Tabel keseluruhan Tahun → Satuan kerja, memakai semua filter; kolom Rekomendasi menghitung
     * rekomendasi unik.
     */
    private function tabelKeseluruhan(array $T, array $kelompok): array
    {
        $urutMaster = [];
        foreach ($kelompok['semua'] as $i => $o) {
            $urutMaster[$o['k']] = $i;
        }
        $kunci = ['rek', 'tugas', 'M', 'BM', 'siptl', 'SS', 'BS', 'BT', 'belumUnggah', 'nilai', 'sisaBpk', 'sisa'];
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
                'M' => $o['M'], 'BM' => $o['BM'], 'siptl' => $o['siptl'], 'SS' => $o['SS'], 'BS' => $o['BS'],
                'BT' => $o['BT'], 'belumUnggah' => $o['belumUnggah'], 'nilai' => $o['nilai'], 'sisaBpk' => $o['sisaBpk'], 'sisa' => $o['sisa'],
            ], Dasbor::perSatker($Ty));
            if ($baris) {
                /* Rekomendasi dihitung sekali per tahun (label baris), bukan dijumlah per satuan kerja. */
                $kelompokTk[] = ['k' => $y, 'baris' => $baris, 'jumlah' => array_merge($jumlah($baris), ['rek' => $rekUnik($Ty)])];
            }
        }
        $semuaBaris = array_merge(...array_map(fn ($g) => $g['baris'], $kelompokTk ?: [['baris' => []]]));

        return [
            'kelompok' => $kelompokTk,
            'total' => array_merge($jumlah($semuaBaris), ['rek' => $rekUnik($T)]),
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
            'kunci' => $pintas,
            'tahun' => $tahun,
            'satker' => $satker !== '' ? (int) $satker : null,
            'namaSatker' => $satker !== '' ? (Satker::find((int) $satker)?->namaPendek() ?? 'Tidak diisi') : null,
            'kolom' => $kolom,
            'daftar' => $daftar,
        ];
    }
}
