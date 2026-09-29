<?php

namespace App\Support;

use App\Enums\HasilTelaah;
use App\Enums\PeranPengguna;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Hitungan Ringkasan (dasbor) — padanan `Prototipe/src/dasbor-uji.js`, fungsi
 * demi fungsi, dengan nama yang sama. Murni: tidak menyentuh basis data dan
 * tidak mengubah model; seluruh aturan domain (nilai diakui, posisi berkas,
 * status BPK) dipinjam dari model, jadi angkanya sama dengan layar lain.
 *
 * SATUAN HITUNGNYA TINDAK LANJUT SATUAN KERJA (Hizkia, 24 Sep 2026: ringkasan
 * utama "seharusnya fokus utama ke tindak lanjut satuan kerja", bukan kemajuan
 * rekomendasi): satu satuan kerja pada satu bentuk tindak lanjut — satu baris
 * `sasarans`, satu baris lembar pemantauan Setba, pemilik rel posisi. Kepala
 * dasbor Excel mereka menghitung ini juga: "Selesai Satker 303, Belum Selesai
 * Satker 9".
 *
 * Cara memfilternya meniru Power BI: satu set filter berlaku untuk semua
 * grafik, dan tiap grafik boleh MENGABAIKAN filter miliknya sendiri — grafik
 * per tahun tetap menggambar seluruh tahun walau satu tahun sedang dipilih.
 *
 * Satu BUTIR = satu rekomendasi yang terlihat, berikut laporannya:
 *
 *     ['rek' => Rekomendasi, 'tem' => Temuan, 'lap' => Laporan,
 *      'semua' => seluruh barisnya yang terlihat, 'baris' => baris yang lolos filter]
 *
 * Filter yang memotong per baris (satuan kerja, meja, bentuk, keadaan
 * memadai, status SIPTL) cukup mempersempit `baris`; status BPK baris tetap
 * dibaca dari `semua` — sama dengan `bagianRek` prototipe, yang menurunkan
 * status bagian dari seluruh barisnya.
 */
class Dasbor
{
    public const FILTER_KOSONG = [
        'tahun' => [], 'jenis' => 'semua', 'satker' => [], 'hasil' => '', 'bpk' => [], 'meja' => [], 'lain' => [],
    ];

    /**
     * Filter tambahan lewat "Tambah filter". Semuanya bersandar pada data
     * master, jadi pilihan baru di master langsung jadi pilihan filter.
     * `baris` memotong rekomendasi ke bagian yang cocok (seperti satuan kerja);
     * yang lain memfilter rekomendasi utuh.
     */
    public const DIM_LAIN = [
        'intern'   => ['nama' => 'Kategori internal', 'ket' => 'Kelompok buatan BPSDM'],
        /* Nilainya pendek ("SPI", "Kepatuhan"), jadi keping filter aktifnya
           diberi sebutan — tanpa itu "SPI" di bar hasil tidak jelas apa. */
        'kategori' => ['nama' => 'Kategori temuan', 'sebut' => 'Temuan', 'ket' => 'Golongan dari pemeriksa'],
        'sifat'    => ['nama' => 'Sifat rekomendasi', 'sebut' => 'Sifat', 'ket' => 'Administratif atau kerugian negara'],
        'bentuk'   => ['nama' => 'Bentuk tindak lanjut', 'ket' => 'Mis. surat teguran, bukti setor', 'baris' => true],
    ];

    public const URUT_LAIN = ['intern', 'kategori', 'sifat', 'bentuk'];

    public const URUT_BPK = ['SS', 'BS', 'BT', 'TD'];

    /* Warna grafik — sama dengan WARNA di DasborUji.jsx, diuji pengukur buta
       warna. "proses": belum memadai yang sedang diproses Setba, UKI, atau
       Inspektorat — abu, supaya yang menonjol hanya bagian yang menunggu
       satuan kerja (kuning). */
    public const WARNA = [
        'M' => 'var(--d-ok)', 'BM' => 'var(--d-belum)',
        'SS' => 'var(--d-ok)', 'BS' => 'var(--d-belum)', 'BT' => 'var(--d-bt)', 'TD' => 'var(--d-td)',
        'masuk' => 'var(--d-masuk)', 'tunggu' => 'var(--d-tunggu)', 'proses' => 'var(--d-proses)',
    ];

    /* Tulisan di dalam potongan berwarna mengikuti terang warnanya: gelap di
       atas hijau dan kuning, putih di atas merah tua dan abu. */
    public const TULISAN_DI = ['M' => '#111827', 'SS' => '#111827', 'BT' => '#111827',
        'BM' => '#FFFFFF', 'BS' => '#FFFFFF', 'TD' => '#FFFFFF'];

    public const NAMA_BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
        'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    /* Meja = siapa yang sedang memegang berkasnya, dari pemegang posisi. */
    public const NAMA_MEJA = ['satker' => 'Satuan kerja', 'setba' => 'Setba', 'uki' => 'UKI',
        'itjen' => 'Inspektorat', 'selesai' => 'Selesai diperiksa'];

    /* Sebutan pendek untuk sumbu grafik di panel sempit. */
    public const PENDEK_MEJA = ['satker' => 'Satker', 'setba' => 'Setba', 'uki' => 'UKI',
        'itjen' => 'Itjen', 'selesai' => 'Selesai'];

    /* ================================================================
       BUTIR DAN BARIS
       ================================================================ */

    /** Rekomendasi yang terlihat → butir, urut seperti datanya. */
    public static function butir(Collection $rekomendasi): array
    {
        return $rekomendasi->map(function (Rekomendasi $r) {
            $semua = $r->daftarSasaran()->values();

            return ['rek' => $r, 'tem' => $r->temuan, 'lap' => $r->temuan->laporan,
                'semua' => $semua, 'baris' => $semua];
        })->values()->all();
    }

    public static function meja(Sasaran $b): string
    {
        return match ($b->pos()->pemegang()) {
            PeranPengguna::SATKER      => 'satker',
            PeranPengguna::SETBA       => 'setba',
            PeranPengguna::UKI         => 'uki',
            PeranPengguna::INSPEKTORAT => 'itjen',
            default                    => 'selesai',
        };
    }

    /** Daftar meja urut rantai posisi; "Selesai" selalu paling ujung. */
    public static function daftarMeja(): array
    {
        return array_map(fn ($k) => ['k' => $k, 'nama' => self::NAMA_MEJA[$k], 'pendek' => self::PENDEK_MEJA[$k]],
            array_keys(self::NAMA_MEJA));
    }

    public static function memadai(Sasaran $b): bool
    {
        return $b->hasil === HasilTelaah::M;
    }

    public static function nilai(Sasaran $b): int
    {
        return (int) $b->nilai;
    }

    public static function sisa(Sasaran $b): int
    {
        return max(0, (int) $b->nilai - $b->nilaiDiakuiItjen());
    }

    public static function sisaBpk(array $x, Sasaran $b): int
    {
        return max(0, (int) $b->nilai - $b->nilaiDiakuiBpk($x['rek']));
    }

    /**
     * Status BPK baris: kalau belum satu baris pun punya status sendiri,
     * status rekomendasinya yang berlaku; kalau sudah ada, baris yang belum
     * diunggah dihitung BT — aturan yang sama dengan `statusRek`.
     */
    public static function statusBpk(array $x, Sasaran $b): string
    {
        $adaBaris = $x['semua']->contains(fn ($y) => $y->status_bpk !== null);

        return $adaBaris ? ($b->status_bpk?->value ?? 'BT') : ($x['rek']->status?->value ?? 'BT');
    }

    public static function lhp(array $x): bool
    {
        return $x['lap']->sumber->melewatiSiptl();
    }

    public static function tahun(array $x): string
    {
        return $x['lap']->tahun();
    }

    public static function bentuk(Sasaran $b): string
    {
        return (string) ($b->tindakan?->bentuk?->nama ?? '');
    }

    public static function satkerKunci(Sasaran $b): int
    {
        return (int) ($b->satker_id ?? 0);
    }

    /** Nilai dimensi tambahan yang memfilter rekomendasi utuh. */
    public static function nilaiLain(string $k, array $x): string
    {
        return (string) match ($k) {
            'intern'   => $x['tem']->kategoriIntern?->nama ?? '',
            'kategori' => $x['tem']->kategori?->nama ?? '',
            'sifat'    => $x['rek']->sifat?->nama ?? '',
            default    => '',
        };
    }

    public static function tglMasuk(array $x): string
    {
        $t = $x['lap']->tgl_terima ?? $x['lap']->tgl_surat;

        return $t ? $t->toDateString() : '';
    }

    /* ================================================================
       FILTER
       ================================================================ */

    public static function adaFilter(array $f): bool
    {
        return $f['tahun'] || $f['jenis'] !== 'semua' || $f['satker'] || $f['hasil'] !== ''
            || $f['bpk'] || $f['meja'] || collect($f['lain'])->contains(fn ($v) => count($v) > 0);
    }

    /**
     * `kecuali`: filter yang dilewati. Satuan kerja, meja, bentuk, keadaan
     * memadai, dan status SIPTL memotong rekomendasi jadi bagian barisnya
     * sendiri — jadi hitungan per tindak lanjut hanya melihat baris yang
     * cocok: "belum memadai" menyisakan baris yang belum memadai, bukan
     * seluruh rekomendasi yang salah satu barisnya belum.
     */
    public static function terapkanFilter(array $items, array $f, array $kecuali = []): array
    {
        $lewat = fn ($k) => in_array($k, $kecuali, true);
        $tahun = ! $lewat('tahun') && $f['tahun'] ? array_flip($f['tahun']) : null;
        $satker = ! $lewat('satker') && $f['satker'] ? array_flip($f['satker']) : null;
        $meja = ! $lewat('meja') && $f['meja'] ? array_flip($f['meja']) : null;
        $bpk = ! $lewat('bpk') && $f['bpk'] ? array_flip($f['bpk']) : null;
        $hasil = $lewat('hasil') ? '' : $f['hasil'];
        $jenis = $lewat('jenis') ? 'semua' : $f['jenis'];
        $lainRek = [];
        $bentuk = null;
        foreach ($f['lain'] as $k => $v) {
            if (! isset(self::DIM_LAIN[$k]) || ! $v || $lewat("lain:$k")) {
                continue;
            }
            if (! empty(self::DIM_LAIN[$k]['baris'])) {
                $bentuk = array_flip($v);
            } else {
                $lainRek[$k] = array_flip($v);
            }
        }

        $keluar = [];
        foreach ($items as $x) {
            if ($jenis !== 'semua' && $x['lap']->sumber->value !== $jenis) {
                continue;
            }
            if ($tahun !== null && ! isset($tahun[self::tahun($x)])) {
                continue;
            }
            /* Nilai kosong ditampung sebagai "" — pilihan "Tidak diisi". */
            foreach ($lainRek as $k => $set) {
                if (! isset($set[self::nilaiLain($k, $x)])) {
                    continue 2;
                }
            }
            if ($satker !== null || $meja !== null || $bentuk !== null || $hasil !== '' || $bpk !== null) {
                /* Status BPK hanya ada pada LHP. LHA tidak pernah masuk SIPTL,
                   jadi filter status BPK dengan sendirinya menyisakan LHP. */
                $lhp = self::lhp($x);
                $baris = $x['baris']->filter(function (Sasaran $b) use ($x, $satker, $meja, $bentuk, $hasil, $bpk, $lhp) {
                    return ($satker === null || isset($satker[self::satkerKunci($b)]))
                        && ($meja === null || isset($meja[self::meja($b)]))
                        && ($bentuk === null || isset($bentuk[self::bentuk($b)]))
                        && ($hasil === '' || (self::memadai($b) ? 'M' : 'BM') === $hasil)
                        && ($bpk === null || ($lhp && isset($bpk[self::statusBpk($x, $b)])));
                })->values();
                if ($baris->isEmpty()) {
                    continue;
                }
                $x['baris'] = $baris;
            }
            $keluar[] = $x;
        }

        return $keluar;
    }

    /**
     * Pilihan yang sudah tidak ada di daftarnya — unit yang dinonaktifkan dan
     * kosong, butir master yang dibuang — tidak ikut memfilter. Tanpa ini satu
     * pilihan yang tidak terlihat lagi bisa menyembunyikan seluruh data.
     */
    public static function bersihkan(array $f, array $sah): array
    {
        $cocok = fn (array $arr, ?array $set) => $set === null ? $arr
            : array_values(array_filter($arr, fn ($v) => in_array($v, $set, true)));
        $lain = [];
        foreach ($f['lain'] as $k => $v) {
            if (isset(self::DIM_LAIN[$k])) {
                $lain[$k] = $cocok($v, $sah['lain'][$k] ?? null);
            }
        }

        return array_merge($f, [
            'tahun'  => $cocok($f['tahun'], $sah['tahun']),
            'satker' => $cocok($f['satker'], $sah['satker']),
            'lain'   => $lain,
        ]);
    }

    /* ================================================================
       ANGKA KARTU
       ================================================================ */

    /**
     * Angka kartu ringkasan, per tindak lanjut satuan kerja: memadai dan
     * belum, di meja siapa yang belum memadai berada, nilai dan sisanya, dan
     * status SIPTL (LHP saja). Jumlah rekomendasi dan laporan di baliknya ikut
     * dihitung — untuk kalimat rincian, dan untuk memilih kata "Memadai" atau
     * "Sesuai".
     */
    public static function hitungTindak(array $K): array
    {
        $o = ['n' => 0, 'M' => 0, 'BM' => 0, 'nilai' => 0, 'sisa' => 0, 'meja' => [], 'jenisBM' => [],
            'bpk' => ['n' => 0, 'SS' => 0, 'BS' => 0, 'BT' => 0, 'TD' => 0]];
        $rek = $lap = $lapLhp = [];
        foreach ($K as $x) {
            $lhp = self::lhp($x);
            $lap[$x['lap']->id] = true;
            if ($lhp) {
                $lapLhp[$x['lap']->id] = true;
            }
            foreach ($x['baris'] as $b) {
                $o['n']++;
                $rek[$x['rek']->id] = true;
                $o['nilai'] += self::nilai($b);
                $o['sisa'] += self::sisa($b);
                if (self::memadai($b)) {
                    $o['M']++;
                } else {
                    $o['BM']++;
                    $j = $x['lap']->sumber->value;
                    $o['jenisBM'][$j] = ($o['jenisBM'][$j] ?? 0) + 1;
                    $m = self::meja($b);
                    $o['meja'][$m] = ($o['meja'][$m] ?? 0) + 1;
                }
                if ($lhp) {
                    $st = self::statusBpk($x, $b);
                    $o['bpk']['n']++;
                    $o['bpk'][$st] = ($o['bpk'][$st] ?? 0) + 1;
                }
            }
        }

        return $o + ['rek' => count($rek), 'lap' => count($lap), 'lapLhp' => count($lapLhp)];
    }

    /**
     * Tindak lanjut belum memadai yang berkasnya sedang di satuan kerja —
     * menunggu tanggapannya — per satuan kerja, bersama jumlah yang belum
     * memadai seluruhnya. Hanya satuan kerja yang punya tunggakan; urut dari
     * yang terbanyak menunggu.
     */
    public static function perTunggu(array $S): array
    {
        $peta = [];
        foreach ($S as $x) {
            foreach ($x['baris'] as $b) {
                if (self::memadai($b)) {
                    continue;
                }
                $k = self::satkerKunci($b);
                $peta[$k] ??= ['k' => $k, 'nama' => $b->satker?->nama ?? 'Tidak diisi', 'tunggu' => 0, 'BM' => 0];
                $peta[$k]['BM']++;
                if (self::meja($b) === 'satker') {
                    $peta[$k]['tunggu']++;
                }
            }
        }
        $baris = array_values(array_filter($peta, fn ($o) => $o['tunggu'] > 0));
        usort($baris, fn ($a, $b) => [$b['tunggu'], $b['BM']] <=> [$a['tunggu'], $a['BM']]
            ?: self::bandingNama($a['nama'], $b['nama']));

        return $baris;
    }

    /**
     * Tindak lanjut per tahun laporan, dibelah memadai dan belum — dan status
     * SIPTL per tahun untuk LHP. Sama dengan baris tahun tabel keseluruhan.
     */
    public static function perTahun(array $Y): array
    {
        $peta = [];
        foreach ($Y as $x) {
            $k = self::tahun($x);
            $peta[$k] ??= ['k' => $k, 'M' => 0, 'BM' => 0, 'nilai' => 0, 'sisa' => 0, 'lhp' => 0,
                'SS' => 0, 'BS' => 0, 'BT' => 0, 'TD' => 0, 'sisaBpk' => 0, 'tugas' => 0];
            $lhp = self::lhp($x);
            foreach ($x['baris'] as $b) {
                $peta[$k]['tugas']++;
                $peta[$k][self::memadai($b) ? 'M' : 'BM']++;
                $peta[$k]['nilai'] += self::nilai($b);
                $peta[$k]['sisa'] += self::sisa($b);
                if ($lhp) {
                    $st = self::statusBpk($x, $b);
                    $peta[$k]['lhp']++;
                    $peta[$k][$st]++;
                    $peta[$k]['sisaBpk'] += self::sisaBpk($x, $b);
                }
            }
        }

        return $peta;
    }

    /**
     * Tindak lanjut per satuan kerja, urut dari tumpukan belum memadai
     * terbanyak. Sisa nilai dua versi, seperti tabel per satker lembar
     * pemantauan — yang SIPTL hanya dari LHP. Untuk tabel keseluruhan juga:
     * nilai bagian satuan kerja, berapa rekomendasi yang melibatkannya, dan
     * status SIPTL tiap tindak lanjut.
     */
    public static function perSatker(array $S): array
    {
        $peta = [];
        $rekPer = [];
        foreach ($S as $x) {
            $lhp = self::lhp($x);
            foreach ($x['baris'] as $b) {
                $k = self::satkerKunci($b);
                $peta[$k] ??= ['k' => $k, 'nama' => $b->satker?->nama ?? 'Tidak diisi',
                    'pendek' => $b->satker?->namaPendek() ?? 'Tidak diisi',
                    'M' => 0, 'BM' => 0, 'sisa' => 0, 'lhp' => 0, 'sisaBpk' => 0, 'nilai' => 0, 'nRek' => 0,
                    'SS' => 0, 'BS' => 0, 'BT' => 0, 'TD' => 0];
                $rekPer[$k][$x['rek']->id] = true;
                $peta[$k][self::memadai($b) ? 'M' : 'BM']++;
                $peta[$k]['nilai'] += self::nilai($b);
                $peta[$k]['sisa'] += self::sisa($b);
                if ($lhp) {
                    $peta[$k]['lhp']++;
                    $peta[$k]['sisaBpk'] += self::sisaBpk($x, $b);
                    $peta[$k][self::statusBpk($x, $b)]++;
                }
            }
        }
        foreach ($peta as $k => $o) {
            $peta[$k]['nRek'] = count($rekPer[$k]);
        }
        $baris = array_values($peta);
        usort($baris, fn ($a, $b) => [$b['BM'], $b['M'] + $b['BM']] <=> [$a['BM'], $a['M'] + $a['BM']]
            ?: self::bandingNama($a['nama'], $b['nama']));

        return $baris;
    }

    /**
     * Rekomendasi di balik satu sel tabel keseluruhan: tahun (kalau ada),
     * satuan kerja (kalau ada), dan kolomnya — aturan yang sama persis dengan
     * `perSatker`, supaya isi daftarnya tepat angka di selnya.
     */
    public static function isiSel(array $T, ?string $tahun, ?int $satker, string $kolom): array
    {
        $rp = ['nilai' => 1, 'sisa' => 1, 'sisaBpk' => 1];
        $out = [];
        foreach ($T as $x) {
            if ($tahun !== null && self::tahun($x) !== $tahun) {
                continue;
            }
            $lhp = self::lhp($x);
            $jumlah = 0;
            $baris = $x['baris']->filter(function (Sasaran $b) use ($x, $satker, $kolom, $lhp, $rp, &$jumlah) {
                if ($satker !== null && self::satkerKunci($b) !== $satker) {
                    return false;
                }
                $v = match (true) {
                    $kolom === 'M'       => self::memadai($b),
                    $kolom === 'BM'      => ! self::memadai($b),
                    $kolom === 'lhp'     => $lhp,
                    in_array($kolom, self::URUT_BPK, true) => $lhp && self::statusBpk($x, $b) === $kolom,
                    $kolom === 'nilai'   => self::nilai($b),
                    $kolom === 'sisa'    => self::sisa($b),
                    $kolom === 'sisaBpk' => $lhp ? self::sisaBpk($x, $b) : 0,
                    default              => 1,
                };
                if (isset($rp[$kolom])) {
                    $jumlah += (int) $v;
                }

                return (bool) $v;
            })->values();
            if ($baris->isNotEmpty()) {
                $out[] = ['x' => $x, 'baris' => $baris, 'rp' => $jumlah];
            }
        }
        if (isset($rp[$kolom])) {
            usort($out, fn ($a, $b) => $b['rp'] <=> $a['rp']);
        } else {
            usort($out, fn ($a, $b) => strnatcmp((string) $a['x']['rek']->kode, (string) $b['x']['rek']->kode));
        }

        return $out;
    }

    /** Tindak lanjut menurut meja yang memegangnya sekarang. */
    public static function perMeja(array $P): array
    {
        $h = array_fill_keys(array_keys(self::NAMA_MEJA), 0);
        foreach ($P as $x) {
            foreach ($x['baris'] as $b) {
                $h[self::meja($b)]++;
            }
        }

        return array_map(fn ($m) => $m + ['n' => $h[$m['k']]], self::daftarMeja());
    }

    /**
     * Versi BPSDM (verifikasi Inspektorat) dan versi SIPTL (putusan BPK),
     * berdampingan — jumlah dan nilainya — per tindak lanjut satuan kerja.
     * BPSDM atas SELURUH pilihan; SIPTL hanya LHP, begitu juga selisih "sudah
     * memadai tapi belum sesuai di SIPTL". Rupiahnya dibelah jadi yang sudah
     * diakui (Memadai/SS) dan sisanya.
     */
    public static function hitungBanding(array $S): array
    {
        $unor = ['M' => ['n' => 0, 'rp' => 0], 'BM' => ['n' => 0, 'rp' => 0]];
        $bpk = array_fill_keys(self::URUT_BPK, ['n' => 0, 'rp' => 0]);
        $n = $nLhp = $beda = 0;
        foreach ($S as $x) {
            $lhp = self::lhp($x);
            foreach ($x['baris'] as $b) {
                $n++;
                $nilai = self::nilai($b);
                $sisa = self::sisa($b);
                $ok = self::memadai($b);
                $unor[$ok ? 'M' : 'BM']['n']++;
                $unor['M']['rp'] += $nilai - $sisa;
                $unor['BM']['rp'] += $sisa;
                if (! $lhp) {
                    continue;
                }
                $nLhp++;
                $st = self::statusBpk($x, $b);
                $bpk[$st]['n']++;
                $sisaB = self::sisaBpk($x, $b);
                $bpk['SS']['rp'] += $nilai - $sisaB;
                if ($st !== 'SS') {
                    $bpk[$st]['rp'] += $sisaB;
                }
                if ($ok && $st !== 'SS' && $st !== 'TD') {
                    $beda++;
                }
            }
        }

        return ['adaLhp' => $nLhp > 0, 'n' => $n, 'nLhp' => $nLhp, 'unor' => $unor, 'bpk' => $bpk, 'beda' => $beda];
    }

    /**
     * Tindak lanjut per kategori internal. Nama dan warnanya dari data
     * master; urut dari tumpukan belum memadai terbanyak.
     */
    public static function perKategori(array $S, array $opsi): array
    {
        $peta = [];
        foreach ($S as $x) {
            $k = self::nilaiLain('intern', $x);
            $peta[$k] ??= ['k' => $k, 'n' => 0, 'M' => 0, 'BM' => 0, 'nilai' => 0, 'sisa' => 0];
            foreach ($x['baris'] as $b) {
                $peta[$k]['n']++;
                $peta[$k]['nilai'] += self::nilai($b);
                $peta[$k]['sisa'] += self::sisa($b);
                $peta[$k][self::memadai($b) ? 'M' : 'BM']++;
            }
        }
        $info = fn ($k) => collect($opsi)->firstWhere('k', $k) ?? ['nama' => $k !== '' ? $k : 'Tidak diisi'];
        $baris = array_map(fn ($o) => $o + ['nama' => $info($o['k'])['nama'], 'warna' => $info($o['k'])['warna'] ?? null],
            array_values($peta));
        usort($baris, fn ($a, $b) => [$b['BM'], $b['n']] <=> [$a['BM'], $a['n']]
            ?: self::bandingNama($a['nama'], $b['nama']));

        return $baris;
    }

    /* ================================================================
       WAKTU
       ================================================================ */

    /**
     * Dua belas bulan terakhir, berakhir di bulan hari ini. `akhir` = tanggal
     * penutup bulannya; bulan berjalan ditutup hari ini.
     */
    public static function bulanTerakhir(string $hariIni, int $n = 12): array
    {
        $t = Carbon::parse($hariIni)->startOfMonth();
        $out = [];
        for ($i = $n - 1; $i >= 0; $i--) {
            $d = $t->copy()->subMonthsNoOverflow($i);
            $out[] = ['kunci' => $d->format('Y-m'), 'th' => (int) $d->year, 'bl' => $d->month - 1,
                'akhir' => $i === 0 ? $hariIni : $d->copy()->endOfMonth()->toDateString()];
        }

        return $out;
    }

    /** Bulan-bulan satu tahun kalender; tahun berjalan berhenti di bulan ini. */
    public static function bulanTahun(int $th, string $hariIni): array
    {
        $kini = Carbon::parse($hariIni);
        $sampai = $th === $kini->year ? $kini->month : 12;
        $out = [];
        for ($bl = 0; $bl < $sampai; $bl++) {
            $awal = Carbon::create($th, $bl + 1, 1);
            $out[] = ['kunci' => $awal->format('Y-m'), 'th' => $th, 'bl' => $bl,
                'akhir' => $th === $kini->year && $bl === $sampai - 1 ? $hariIni : $awal->copy()->endOfMonth()->toDateString()];
        }

        return $out;
    }

    /**
     * Putusan Inspektorat atas satu baris, urut tanggal. Tanda memadai pada
     * baris hanya dipasang sesudah Inspektorat memutus, jadi jejak ini sama
     * dengan `hasil` barisnya.
     */
    public static function putusanItjen(Sasaran $b): Collection
    {
        return $b->riwayatStatus
            ->filter(fn ($j) => $j->sumber === 'Itjen' && $j->tanggal)
            ->sortBy(fn ($j) => [$j->tanggal->toDateString(), $j->id])
            ->values();
    }

    public static function keadaanPada(Sasaran $b, string $t): string
    {
        $k = '';
        foreach (self::putusanItjen($b) as $j) {
            if ($j->tanggal->toDateString() > $t) {
                break;
            }
            $k = $j->ke;
        }

        return $k;
    }

    /**
     * Tanggal satu tindak lanjut dinyatakan memadai: putusan terakhir
     * Inspektorat atas baris itu. Kosong kalau barisnya belum memadai, atau
     * putusan terakhirnya bukan memadai.
     */
    public static function tanggalMemadai(Sasaran $b): string
    {
        if (! self::memadai($b)) {
            return '';
        }
        $akhir = self::putusanItjen($b)->last();

        return $akhir && $akhir->ke === 'M' ? $akhir->tanggal->toDateString() : '';
    }

    /** Tahun yang punya kejadian di data — untuk pilihan rentang grafik per bulan. Terbaru dulu. */
    public static function tahunKejadian(array $items): array
    {
        $s = [];
        foreach ($items as $x) {
            $t = substr(self::tglMasuk($x), 0, 4);
            if ($t !== '') {
                $s[$t] = true;
            }
            foreach ($x['baris'] as $b) {
                $m = substr(self::tanggalMemadai($b), 0, 4);
                if ($m !== '') {
                    $s[$m] = true;
                }
            }
        }
        $s = array_map('strval', array_keys($s));
        rsort($s, SORT_STRING);

        return $s;
    }

    /** Tindak lanjut masuk (laporannya diterima) dan selesai (dinyatakan memadai) per bulan. */
    public static function tren(array $T, array $bulan): array
    {
        $idx = array_flip(array_column($bulan, 'kunci'));
        $masuk = array_fill(0, count($bulan), 0);
        $selesai = array_fill(0, count($bulan), 0);
        foreach ($T as $x) {
            $t = substr(self::tglMasuk($x), 0, 7);
            foreach ($x['baris'] as $b) {
                if (isset($idx[$t])) {
                    $masuk[$idx[$t]]++;
                }
                $s = substr(self::tanggalMemadai($b), 0, 7);
                if (isset($idx[$s])) {
                    $selesai[$idx[$s]]++;
                }
            }
        }

        return ['masuk' => $masuk, 'selesai' => $selesai];
    }

    /**
     * Jumlah tindak lanjut belum memadai pada akhir tiap bulan — garis kecil
     * di kartu "Belum memadai". Titik terakhir dibaca dari keadaan sekarang,
     * supaya selalu sama dengan angka besarnya.
     */
    public static function tumpukan(array $K, array $bulan): array
    {
        $akhirI = count($bulan) - 1;

        return array_map(function ($bl, $i) use ($K, $akhirI) {
            $kini = $i === $akhirI;
            $n = 0;
            foreach ($K as $x) {
                $t = self::tglMasuk($x);
                if (! $kini && ($t === '' || $t > $bl['akhir'])) {
                    continue;
                }
                foreach ($x['baris'] as $b) {
                    if ($kini ? ! self::memadai($b) : self::keadaanPada($b, $bl['akhir']) !== 'M') {
                        $n++;
                    }
                }
            }

            return $n;
        }, $bulan, array_keys($bulan));
    }

    /**
     * Lima tindak lanjut belum memadai dengan sisa nilai terbesar; yang
     * bernilai sama diurutkan dari laporan yang paling lama diterima.
     */
    public static function perluPerhatian(array $T, int $n = 5): array
    {
        $out = [];
        foreach ($T as $x) {
            foreach ($x['baris'] as $b) {
                if (! self::memadai($b)) {
                    $out[] = ['x' => $x, 'b' => $b, 'sisa' => self::sisa($b)];
                }
            }
        }
        usort($out, fn ($p, $q) => $q['sisa'] <=> $p['sisa'] ?: strcmp(self::tglMasuk($p['x']), self::tglMasuk($q['x'])));

        return array_slice($out, 0, $n);
    }

    /**
     * Pembuka baku uraian ("Menteri Pekerjaan Umum agar memerintahkan Kepala
     * BPSDM untuk …") dibuang dari TAMPILAN saja — teks utuhnya tetap di
     * keterangan tombol dan di rincian.
     */
    public static function pokokUraian(?string $t): string
    {
        $t = (string) $t;
        $s = preg_replace('/^(?:Menteri Pekerjaan Umum(?: dan Perumahan Rakyat)?|Kepala BPSDM) agar (?:memerintahkan Kepala BPSDM (?:untuk )?)?/u', '', $t);

        return $s === $t || $s === '' ? $t : mb_strtoupper(mb_substr($s, 0, 1)).mb_substr($s, 1);
    }

    /* ================================================================
       ISI FILTER
       ================================================================ */

    public static function daftarTahun(array $items): array
    {
        $t = array_values(array_unique(array_map(fn ($x) => self::tahun($x), $items)));
        sort($t, SORT_STRING);

        return $t;
    }

    /**
     * Satuan kerja untuk filter, mengikuti data master saat itu juga: balai
     * wilayah disebut kotanya, urut nomor wilayah; unit lain nama pendeknya,
     * urut data master; unit nonaktif tetap ditawarkan selama masih punya
     * data; nama yang ada di data tapi tidak ada di master tetap ditawarkan
     * di kelompoknya sendiri.
     *
     * @param  Collection  $unitKerja  satker urut data master
     */
    public static function kelompokSatker(Collection $unitKerja, array $items): array
    {
        $adaData = [];
        foreach ($items as $x) {
            foreach ($x['semua'] as $b) {
                if ($b->satker_id) {
                    $adaData[$b->satker_id] = $b->satker;
                }
            }
        }
        $wilayah = $lain = $luar = [];
        $sudah = [];
        foreach ($unitKerja as $u) {
            if (! $u->aktif && ! isset($adaData[$u->id])) {
                continue;
            }
            $sudah[$u->id] = true;
            $pdk = $u->namaPendek();
            $dasar = ['k' => (int) $u->id, 'pendek' => $pdk, 'lengkap' => $pdk, 'nonaktif' => ! $u->aktif,
                'panjang' => $u->nama];
            if (preg_match('/Wilayah\s+([IVXL]+)\s+(.+)$/u', $u->nama, $m)) {
                $wilayah[] = $dasar + ['nama' => $m[2], 'urut' => self::romawi($m[1]), 'grup' => 'Balai wilayah'];
            } else {
                $lain[] = $dasar + ['nama' => $pdk, 'grup' => 'Unit lain'];
            }
        }
        $sisa = array_filter($adaData, fn ($s, $id) => ! isset($sudah[$id]), ARRAY_FILTER_USE_BOTH);
        uasort($sisa, fn ($a, $b) => self::bandingNama($a->nama, $b->nama));
        foreach ($sisa as $id => $s) {
            $pdk = $s->namaPendek();
            $luar[] = ['k' => (int) $id, 'pendek' => $pdk, 'lengkap' => $pdk, 'nonaktif' => false,
                'panjang' => $s->nama, 'nama' => $pdk, 'grup' => 'Tidak ada di data master'];
        }
        usort($wilayah, fn ($a, $b) => $a['urut'] <=> $b['urut']);

        return ['wilayah' => $wilayah, 'lain' => $lain, 'luar' => $luar, 'semua' => array_merge($wilayah, $lain, $luar)];
    }

    private static function romawi(string $s): int
    {
        $nilai = ['I' => 1, 'V' => 5, 'X' => 10, 'L' => 50];
        $a = 0;
        $c = str_split($s);
        foreach ($c as $i => $h) {
            $v = $nilai[$h] ?? 0;
            $a += ($nilai[$c[$i + 1] ?? ''] ?? 0) > $v ? -$v : $v;
        }

        return $a;
    }

    /**
     * Pilihan untuk filter tambahan. Urutan, kelompok, dan warnanya dari
     * data master; nilai di data yang sudah tidak ada di master tetap
     * ditawarkan; butir master nonaktif yang tidak terpakai disembunyikan.
     * `hanyaTerpakai` untuk daftar baku yang panjang (bentuk tindak lanjut).
     *
     * @param  array  $master  [['nama', 'aktif', 'grup'?, 'warna'?], …]
     */
    public static function opsiLain(string $k, array $master, array $items, bool $hanyaTerpakai = false): array
    {
        $pakai = [];
        foreach ($items as $x) {
            if (! empty(self::DIM_LAIN[$k]['baris'])) {
                foreach ($x['semua'] as $b) {
                    $pakai[self::bentuk($b)] = true;
                }
            } else {
                $pakai[self::nilaiLain($k, $x)] = true;
            }
        }
        $keluar = [];
        $sudah = [];
        $tambah = function (array $o) use (&$keluar, &$sudah) {
            if (! isset($sudah[$o['k']])) {
                $sudah[$o['k']] = true;
                $keluar[] = $o;
            }
        };
        foreach ($master as $m) {
            if (! isset($pakai[$m['nama']]) && (! $m['aktif'] || $hanyaTerpakai)) {
                continue;
            }
            $tambah(['k' => $m['nama'], 'nama' => $m['nama'], 'grup' => $m['grup'] ?? null,
                'warna' => $m['warna'] ?? null, 'nonaktif' => ! $m['aktif']]);
        }
        $lepas = array_filter(array_map('strval', array_keys($pakai)), fn ($v) => $v !== '' && ! isset($sudah[$v]));
        usort($lepas, fn ($a, $b) => self::bandingNama($a, $b));
        foreach ($lepas as $v) {
            $tambah(['k' => $v, 'nama' => $v, 'grup' => 'Tidak ada di data master', 'warna' => null, 'nonaktif' => false]);
        }
        if (isset($pakai[''])) {
            $adaGrup = collect($keluar)->contains(fn ($o) => ! empty($o['grup']));
            $tambah(['k' => '', 'nama' => 'Tidak diisi', 'grup' => $adaGrup ? 'Lainnya' : null, 'warna' => null, 'nonaktif' => false]);
        }

        return $keluar;
    }

    /**
     * Berapa tindak lanjut untuk tiap pilihan sebuah filter. `S` sudah
     * difilter dengan filter LAIN, jadi angkanya menjawab "kalau ini dipilih
     * juga, berapa yang tersisa".
     */
    public static function hitungPilihan(string $k, array $S): array
    {
        $h = [];
        $naik = function ($v, $n = 1) use (&$h) {
            $h[$v] = ($h[$v] ?? 0) + $n;
        };
        foreach ($S as $x) {
            $baris = $x['baris'];
            if ($k === 'satker') {
                foreach ($baris as $b) {
                    $naik(self::satkerKunci($b));
                }
            } elseif ($k === 'tahun') {
                $naik(self::tahun($x), $baris->count());
            } elseif ($k === 'jenis') {
                $naik($x['lap']->sumber->value, $baris->count());
            } elseif (! empty(self::DIM_LAIN[$k]['baris'])) {
                foreach ($baris as $b) {
                    $naik(self::bentuk($b));
                }
            } elseif (isset(self::DIM_LAIN[$k])) {
                $naik(self::nilaiLain($k, $x), $baris->count());
            }
        }

        return $h;
    }

    /**
     * Skala sumbu: kelipatan 1, 2, atau 5 yang enak dibaca, kira-kira tiga
     * garis. Yang dihitung selalu jumlah, jadi langkahnya paling kecil satu.
     */
    public static function skala(float $maks, int $garis = 3): array
    {
        $m = max(1, $maks);
        $kasar = $m / $garis;
        $pangkat = 10 ** floor(log10($kasar));
        $langkah = null;
        foreach ([1, 2, 5, 10] as $x) {
            if ($x * $pangkat >= $kasar) {
                $langkah = $x * $pangkat;
                break;
            }
        }
        $langkah = max(1, $langkah ?? $pangkat * 10);
        $atas = ceil($m / $langkah) * $langkah;
        $tanda = [];
        for ($v = 0; $v <= $atas + 1e-9; $v += $langkah) {
            $tanda[] = (int) round($v);
        }

        return ['atas' => $atas, 'tanda' => $tanda];
    }

    public static function persen(int|float $a, int|float $b): int
    {
        return $b > 0 ? (int) round($a / $b * 100) : 0;
    }

    /**
     * Pembanding nama, sama dengan `localeCompare(…, "id")` prototipe: ICU,
     * lewat Collator. `strnatcasecmp` melompati spasi, jadi "Wilayah V
     * Yogyakarta" terbaca "WilayahVYogyakarta" dan jatuh sesudah "Wilayah VI
     * Surabaya" — urutan yang seri pun jadi berbeda dari prototipe.
     */
    public static function bandingNama(string $a, string $b): int
    {
        static $kolator = null;
        if (class_exists(\Collator::class)) {
            $kolator ??= new \Collator('id_ID');

            return $kolator->compare($a, $b);
        }

        return strcasecmp($a, $b);
    }
}
