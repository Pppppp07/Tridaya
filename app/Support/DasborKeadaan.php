<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Keadaan layar Ringkasan (dasbor), dibawa ALAMAT halaman — padanan
 * `filterDasbor` di App prototipe. Tampilan yang sedang dilihat bisa disalin
 * dan dikirim apa adanya, tombol Kembali browser mengembalikan keadaan
 * sebelumnya, dan kembali dari rincian rekomendasi mendarat di tempat yang
 * sama.
 *
 * Seluruh halaman satu formulir GET. Tiap tombol mengirim satu perbuatan
 * (`ubah=satker:5`, `ubah=kartu:M`, …) bersama keadaannya sekarang (masukan
 * tersembunyi); server menerapkannya lalu mengalihkan ke alamat bersihnya —
 * menyegarkan halaman tidak mengulangi perbuatannya. Tombolnya tetap tombol,
 * persis seperti prototipe, jadi gayanya disalin tanpa diubah.
 *
 *     jenis=LHP  tahun[]  satker[]  hasil=BM  bpk[]  meja[]
 *     pasang[]=intern  lain[intern][]            filter tambahan
 *     kartu=M  hal=tabel  buka[]  urut=BM&arah=naik
 *     periode=2025  tabel[]=satker  semua[]=satker  pintas=2024|5|BM
 */
class DasborKeadaan
{
    public const KARTU = ['M', 'BM', 'tunggu', 'sisa', 'ss'];

    /* Kolom tabel keseluruhan yang bisa jadi pengurut. */
    public const KOLOM_URUT = ['tahun', 'nama', 'rek', 'tugas', 'M', 'BM', 'pct', 'lhp', 'SS', 'BS', 'BT', 'TD',
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

        /* Filter tambahan: baris yang dipasang (urut pemasangannya) dan
           isinya. Baris yang dipasang tanpa pilihan tetap tampil. */
        $lainIsi = $req->query('lain');
        $lain = [];
        foreach ($daftar('pasang') as $k) {
            if (! isset(Dasbor::DIM_LAIN[$k])) {
                continue;
            }
            $v = is_array($lainIsi) && is_array($lainIsi[$k] ?? null) ? $lainIsi[$k] : [];
            $lain[$k] = array_values(array_unique(array_map('strval', array_filter($v, 'is_scalar'))));
        }

        $jenis = (string) $req->query('jenis', 'semua');
        $hasil = (string) $req->query('hasil', '');
        $urutK = (string) $req->query('urut', self::URUT_AWAL['k']);
        $pintas = (string) $req->query('pintas', '');

        return [
            'f' => [
                'tahun'  => array_values(array_filter($daftar('tahun'), fn ($t) => preg_match('/^\d{4}$|^—$/u', $t))),
                'jenis'  => in_array($jenis, ['LHP', 'LHA'], true) ? $jenis : 'semua',
                'satker' => array_values(array_unique(array_map('intval', array_filter($daftar('satker'), 'ctype_digit')))),
                'hasil'  => in_array($hasil, ['M', 'BM'], true) ? $hasil : '',
                'bpk'    => $pilih('bpk', Dasbor::URUT_BPK),
                'meja'   => $pilih('meja', array_keys(Dasbor::NAMA_MEJA)),
                'lain'   => $lain,
            ],
            'kartu'   => in_array($req->query('kartu'), self::KARTU, true) ? $req->query('kartu') : '',
            'hal'     => $req->query('hal') === 'tabel' ? 'tabel' : 'dasbor',
            'buka'    => array_values(array_filter($daftar('buka'), fn ($t) => preg_match('/^\d{4}$|^—$/u', $t))),
            'urut'    => ['k' => in_array($urutK, self::KOLOM_URUT, true) ? $urutK : self::URUT_AWAL['k'],
                'arah' => $req->query('arah') === 'naik' ? 1 : (in_array($urutK, self::KOLOM_URUT, true) ? -1 : self::URUT_AWAL['arah'])],
            'periode' => preg_match('/^\d{4}$/', (string) $req->query('periode')) ? (string) $req->query('periode') : '12',
            'tabel'   => $pilih('tabel', self::PANEL_TABEL),
            'semua'   => $pilih('semua', self::PANEL_LENGKAP),
            'pintas'  => preg_match('/^(\d{4}|—)?\|\d*\|[A-Za-z]+$/u', $pintas) ? $pintas : '',
        ];
    }

    /**
     * Keadaan → isi alamat. Nilai bawaan tidak ditulis, jadi alamat keadaan
     * awal cukup `/ringkasan`.
     */
    public static function keQuery(array $k): array
    {
        $f = $k['f'];
        $q = [];
        if ($f['jenis'] !== 'semua') {
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
        if ($k['periode'] !== '12') {
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

    /**
     * Masukan tersembunyi untuk formulir: keadaan sekarang, SELAIN yang
     * dipegang kontrol yang kelihatan (rentang grafik per bulan).
     */
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

    /* ================================================================
       PERBUATAN
       ================================================================ */

    private static function balik(array $arr, $v): array
    {
        return in_array($v, $arr, true) ? array_values(array_filter($arr, fn ($x) => $x !== $v)) : [...$arr, $v];
    }

    /** Kosongkan filter — baris tambahan yang dipasang dan keadaan tampilan tetap. */
    public static function kosongkan(array $k): array
    {
        $k['f'] = array_merge(Dasbor::FILTER_KOSONG, ['lain' => array_map(fn () => [], $k['f']['lain'])]);

        return $k;
    }

    /**
     * Terapkan satu perbuatan dari tombol. Mengembalikan keadaan baru;
     * perbuatan yang tidak dikenal diabaikan.
     *
     * @param  callable|null  $lhp  jenis laporan → masuk SIPTL atau tidak
     */
    public static function terapkan(array $k, string $ubah, ?callable $lhp = null): array
    {
        [$apa, $isi] = array_pad(explode(':', $ubah, 2), 2, '');
        $f = &$k['f'];

        switch ($apa) {
            case 'jenis':
                $baru = in_array($isi, ['LHP', 'LHA'], true) && $f['jenis'] !== $isi ? $isi : 'semua';
                $f['jenis'] = $baru;
                /* Jenis tanpa SIPTL tidak punya status BPK untuk difilter. */
                if ($baru !== 'semua' && $lhp && ! $lhp($baru)) {
                    $f['bpk'] = [];
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
                if (in_array($isi, Dasbor::URUT_BPK, true)) {
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
                /* Klik batang kategori internal memasang barisnya sekalian
                   kalau belum dipasang — sama dengan `ubahLain` prototipe. */
                [$d, $v] = array_pad(explode(':', $isi, 2), 2, null);
                if (isset(Dasbor::DIM_LAIN[$d])) {
                    $f['lain'][$d] ??= [];
                    $f['lain'][$d] = $v === null ? [] : self::balik($f['lain'][$d], $v);
                }
                break;
            case 'setel':
                /* Pilihan utuh dari menu "+N lainnya" (pilih semua, pilih yang
                   cocok, per kelompok): `setel:satker:[1,5]`, `setel:lain:intern:["…"]`. */
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
                } elseif (array_key_exists($isi, $f)) {
                    $f[$isi] = Dasbor::FILTER_KOSONG[$isi];
                }
                break;
            case 'kosongkan':
                unset($f);
                $k = self::kosongkan($k);
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
