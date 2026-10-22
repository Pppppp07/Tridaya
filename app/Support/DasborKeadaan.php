<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Keadaan Dashboard dibawa alamat halaman (bisa disalin, tombol Kembali bekerja). Satu formulir
 * GET: tiap tombol mengirim satu perbuatan (`ubah=satker:5`), server menerapkannya lalu mengalihkan
 * ke alamat bersihnya.
 */
class DasborKeadaan
{
    /* Tanpa kartu "Belum memadai": rincian kartu pertama sudah memuatnya. */
    public const KARTU = ['M', 'tunggu', 'sisa', 'ss'];

    /* Kolom tabel keseluruhan yang bisa jadi pengurut. */
    public const KOLOM_URUT = ['tahun', 'nama', 'rek', 'tugas', 'M', 'BM', 'pct', 'siptl', 'SS', 'BS', 'BT', 'belumUnggah',
        'nilai', 'sisaBpk', 'sisa'];

    /* Panel yang punya tampilan tabel, dan panel yang punya "Semua (N)". */
    public const PANEL_TABEL = ['status', 'tahunsiptl', 'sisasatker', 'sisatahun', 'satker', 'tunggu', 'meja',
        'kategori', 'tren'];

    public const PANEL_LENGKAP = ['satker', 'kategori', 'sisasatker', 'tunggu'];

    public const URUT_AWAL = ['k' => 'BM', 'arah' => -1];

    /** Keadaan dari alamat (atau kiriman formulir), sudah dibersihkan bentuknya. */
    public static function dari(Request $req): array
    {
        $daftar = function (string $k) use ($req): array {
            $v = $req->query($k);
            if (! is_array($v)) {
                return [];
            }

            return array_values(array_unique(array_map('strval', array_filter($v, 'is_scalar'))));
        };
        $pilih = fn (string $k, array $sah) => array_values(array_intersect($daftar($k), $sah));

        /* Filter tambahan: baris yang dipasang (urut pemasangan) dan isinya. */
        $lainIsi = $req->query('lain');
        $lain = [];
        foreach ($daftar('pasang') as $k) {
            if (! isset(Dasbor::DIM_LAIN[$k])) {
                continue;
            }
            $v = is_array($lainIsi) && is_array($lainIsi[$k] ?? null) ? $lainIsi[$k] : [];
            $lain[$k] = array_values(array_unique(array_map('strval', array_filter($v, 'is_scalar'))));
        }

        $jenis = (string) $req->query('jenis', '');
        $hasil = (string) $req->query('hasil', '');
        $urutK = (string) $req->query('urut', self::URUT_AWAL['k']);
        $pintas = (string) $req->query('pintas', '');

        return [
            'f' => [
                'tahun'  => array_values(array_filter($daftar('tahun'), fn ($t) => preg_match('/^\d{4}$|^—$/u', $t))),
                'jenis'  => in_array($jenis, ['LHP', 'LHA'], true) ? $jenis : Dasbor::JENIS_AWAL,
                'satker' => array_values(array_unique(array_map('intval', array_filter($daftar('satker'), 'ctype_digit')))),
                'hasil'  => in_array($hasil, ['M', 'BM'], true) ? $hasil : '',
                'bpk'    => $pilih('bpk', Dasbor::FILTER_BPK),
                'meja'   => $pilih('meja', array_keys(Dasbor::NAMA_MEJA)),
                'lain'   => $lain,
            ],
            /* Tab data: bukan filter, jadi "Hapus filter" tidak mengubahnya. */
            'data'    => is_string($d = $req->query('data')) && isset(Dasbor::DATA[$d]) ? $d : 'semua',
            'kartu'   => in_array($req->query('kartu'), self::KARTU, true) ? $req->query('kartu') : '',
            'hal'     => $req->query('hal') === 'tabel' ? 'tabel' : 'dasbor',
            'buka'    => array_values(array_filter($daftar('buka'), fn ($t) => preg_match('/^\d{4}$|^—$/u', $t))),
            'urut'    => ['k' => in_array($urutK, self::KOLOM_URUT, true) ? $urutK : self::URUT_AWAL['k'],
                'arah' => $req->query('arah') === 'naik' ? 1 : (in_array($urutK, self::KOLOM_URUT, true) ? -1 : self::URUT_AWAL['arah'])],
            /* Tahun grafik per bulan; kosong = tahun terbaru di data. */
            'periode' => preg_match('/^\d{4}$/', (string) $req->query('periode')) ? (string) $req->query('periode') : '',
            'tabel'   => $pilih('tabel', self::PANEL_TABEL),
            'semua'   => $pilih('semua', self::PANEL_LENGKAP),
            /* Kolom pintasan harus kolom tabel yang ada; tautan lama bisa menyebut kolom yang sudah dibuang. */
            'pintas'  => preg_match('/^(\d{4}|—)?\|\d*\|[A-Za-z]+$/u', $pintas)
                && in_array(substr((string) strrchr($pintas, '|'), 1), self::KOLOM_URUT, true) ? $pintas : '',
        ];
    }

    /** Keadaan → isi alamat; nilai bawaan tidak ditulis. */
    public static function keQuery(array $k): array
    {
        $f = $k['f'];
        $q = [];
        if (($k['data'] ?? 'semua') !== 'semua') {
            $q['data'] = $k['data'];
        }
        if ($f['jenis'] !== Dasbor::JENIS_AWAL) {
            $q['jenis'] = $f['jenis'];
        }
        foreach (['tahun', 'satker', 'bpk', 'meja'] as $d) {
            if ($f[$d]) {
                $q[$d] = array_values($f[$d]);
            }
        }
        if ($f['hasil'] !== '') {
            $q['hasil'] = $f['hasil'];
        }
        if ($f['lain']) {
            $q['pasang'] = array_keys($f['lain']);
            foreach ($f['lain'] as $d => $v) {
                if ($v) {
                    $q['lain'][$d] = array_values($v);
                }
            }
        }
        if ($k['kartu'] !== '') {
            $q['kartu'] = $k['kartu'];
        }
        if ($k['hal'] === 'tabel') {
            $q['hal'] = 'tabel';
        }
        if ($k['buka']) {
            $q['buka'] = array_values($k['buka']);
        }
        if ($k['urut'] !== self::URUT_AWAL) {
            $q['urut'] = $k['urut']['k'];
            $q['arah'] = $k['urut']['arah'] > 0 ? 'naik' : 'turun';
        }
        if ($k['periode'] !== '') {
            $q['periode'] = $k['periode'];
        }
        foreach (['tabel', 'semua'] as $d) {
            if ($k[$d]) {
                $q[$d] = array_values($k[$d]);
            }
        }
        if ($k['pintas'] !== '') {
            $q['pintas'] = $k['pintas'];
        }

        return $q;
    }

    public static function alamat(array $k, string $jangkar = ''): string
    {
        return route('ringkasan', self::keQuery($k)).($jangkar !== '' ? '#'.$jangkar : '');
    }

    /** Masukan tersembunyi: keadaan sekarang selain yang dipegang kontrol yang kelihatan. */
    public static function tersembunyi(array $k): array
    {
        $q = self::keQuery($k);
        unset($q['periode'], $q['pintas']);
        $keluar = [];
        $ratakan = function ($nama, $v) use (&$ratakan, &$keluar) {
            if (is_array($v)) {
                foreach ($v as $kk => $isi) {
                    $ratakan(is_int($kk) ? $nama.'[]' : $nama.'['.$kk.']', $isi);
                }
            } else {
                $keluar[] = [$nama, (string) $v];
            }
        };
        foreach ($q as $nama => $v) {
            $ratakan($nama, $v);
        }

        return $keluar;
    }

    /* ---- Perbuatan ---- */

    private static function balik(array $arr, $v): array
    {
        return in_array($v, $arr, true) ? array_values(array_filter($arr, fn ($x) => $x !== $v)) : [...$arr, $v];
    }

    /** Kosongkan filter — jenis laporan, baris tambahan yang dipasang, dan keadaan tampilan tetap. */
    public static function kosongkan(array $k): array
    {
        $k['f'] = array_merge(Dasbor::FILTER_KOSONG, ['jenis' => $k['f']['jenis'],
            'lain' => array_map(fn () => [], $k['f']['lain'])]);

        return $k;
    }

    /** Terapkan satu perbuatan dari tombol; yang tidak dikenal diabaikan. */
    public static function terapkan(array $k, string $ubah, ?callable $lhp = null): array
    {
        [$apa, $isi] = array_pad(explode(':', $ubah, 2), 2, '');
        $f = &$k['f'];

        switch ($apa) {
            case 'jenis':
                /* Selalu satu jenis: menekan yang sedang berlaku tidak melepasnya. */
                if (in_array($isi, ['LHP', 'LHA'], true)) {
                    $f['jenis'] = $isi;
                    /* Jenis tanpa SIPTL tidak punya status BPK untuk difilter. */
                    if ($lhp && ! $lhp($isi)) {
                        $f['bpk'] = [];
                    }
                }
                break;
            case 'tahun':
                $f['tahun'] = $isi === '' ? [] : self::balik($f['tahun'], $isi);
                break;
            case 'satker':
                $f['satker'] = $isi === '' ? [] : self::balik($f['satker'], (int) $isi);
                break;
            case 'hasil':
                $f['hasil'] = in_array($isi, ['M', 'BM'], true) && $f['hasil'] !== $isi ? $isi : '';
                break;
            case 'bpk':
                if (in_array($isi, Dasbor::FILTER_BPK, true)) {
                    $f['bpk'] = self::balik($f['bpk'], $isi);
                } elseif ($isi === '') {
                    $f['bpk'] = [];
                }
                break;
            case 'meja':
                if (isset(Dasbor::NAMA_MEJA[$isi])) {
                    $f['meja'] = self::balik($f['meja'], $isi);
                } elseif ($isi === '') {
                    $f['meja'] = [];
                }
                break;
            case 'lain':
                /* Klik batang kategori internal memasang barisnya bila belum dipasang. */
                [$d, $v] = array_pad(explode(':', $isi, 2), 2, null);
                if (isset(Dasbor::DIM_LAIN[$d])) {
                    $f['lain'][$d] ??= [];
                    $f['lain'][$d] = $v === null ? [] : self::balik($f['lain'][$d], $v);
                }
                break;
            case 'setel':
                /* Pilihan utuh dari menu "+N lainnya": `setel:satker:[1,5]`,
                   `setel:lain:intern:["…"]`. */
                [$d, $json] = array_pad(explode(':', $isi, 2), 2, '[]');
                if ($d === 'lain') {
                    [$d, $json] = array_pad(explode(':', $json, 2), 2, '[]');
                    $v = json_decode($json, true);
                    if (isset($f['lain'][$d]) && is_array($v)) {
                        $f['lain'][$d] = array_values(array_unique(array_map('strval', array_filter($v, 'is_scalar'))));
                    }
                } elseif (in_array($d, ['tahun', 'satker'], true)) {
                    $v = json_decode($json, true);
                    if (is_array($v)) {
                        $v = array_values(array_unique(array_filter($v, 'is_scalar')));
                        $f[$d] = $d === 'satker' ? array_map('intval', $v) : array_map('strval', $v);
                    }
                }
                break;
            case 'pasang':
                if (isset(Dasbor::DIM_LAIN[$isi]) && ! isset($f['lain'][$isi])) {
                    $f['lain'][$isi] = [];
                }
                break;
            case 'lepas':
                unset($f['lain'][$isi]);
                break;
            case 'hapus':
                /* Keping filter aktif di bar hasil. */
                if (str_starts_with($isi, 'lain:')) {
                    $d = substr($isi, 5);
                    if (isset($f['lain'][$d])) {
                        $f['lain'][$d] = [];
                    }
                } elseif ($isi !== 'jenis' && array_key_exists($isi, $f)) {
                    $f[$isi] = Dasbor::FILTER_KOSONG[$isi];
                }
                break;
            case 'kosongkan':
                unset($f);
                $k = self::kosongkan($k);
                break;
            case 'data':
                if (isset(Dasbor::DATA[$isi])) {
                    $k['data'] = $isi;
                    $k['pintas'] = '';
                }
                break;
            case 'kartu':
                $k['kartu'] = in_array($isi, self::KARTU, true) && $k['kartu'] !== $isi ? $isi : '';
                break;
            case 'hal':
                $k['hal'] = $isi === 'tabel' ? 'tabel' : 'dasbor';
                $k['pintas'] = '';
                break;
            case 'buka':
                $k['buka'] = self::balik($k['buka'], $isi);
                break;
            case 'bukasemua':
                $k['buka'] = $isi === '' ? [] : array_values(array_filter(explode(',', $isi), 'strlen'));
                break;
            case 'urut':
                if (in_array($isi, self::KOLOM_URUT, true)) {
                    $k['urut'] = $k['urut']['k'] === $isi
                        ? ['k' => $isi, 'arah' => -$k['urut']['arah']]
                        : ['k' => $isi, 'arah' => $isi === 'nama' ? 1 : -1];
                }
                break;
            case 'tabel':
                if (in_array($isi, self::PANEL_TABEL, true)) {
                    $k['tabel'] = self::balik($k['tabel'], $isi);
                }
                break;
            case 'semua':
                if (in_array($isi, self::PANEL_LENGKAP, true)) {
                    $k['semua'] = self::balik($k['semua'], $isi);
                }
                break;
            case 'pintas':
                $k['pintas'] = $isi;
                break;
            case 'tutup':
                $k['pintas'] = '';
                break;
        }

        return $k;
    }
}
