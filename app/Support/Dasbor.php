<?php

namespace App\Support;

use App\Enums\HasilTelaah;
use App\Enums\PeranPengguna;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Hitungan Dashboard. Murni: tidak menyentuh basis data; aturan domain dipinjam dari model. Satuan
 * hitungnya tindak lanjut satuan kerja (satu baris `sasarans`). Satu set filter untuk semua grafik;
 * tiap grafik boleh mengabaikan filternya sendiri.
 * Butir = ['rek', 'tem', 'lap', 'semua' => seluruh baris terlihat, 'baris' => baris yang lolos
 * filter].
 */
class Dasbor
{
    /* Jenis laporan selalu satu (tanpa "Semua"): LHP dan LHA tidak digabung dalam satu angka. */
    public const JENIS_AWAL = 'LHP';

    public const FILTER_KOSONG = [
        'tahun' => [], 'jenis' => self::JENIS_AWAL, 'satker' => [], 'hasil' => '', 'bpk' => [], 'meja' => [], 'lain' => [],
    ];

    /**
     * Filter tambahan dari data master. `baris` memotong rekomendasi ke bagian yang cocok; yang
     * lain memfilter rekomendasi utuh.
     */
    public const DIM_LAIN = [
        'intern' => ['nama' => 'Kategori internal', 'ket' => 'Pengelompokan temuan untuk pemantauan internal BPSDM'],
        /* Nilainya pendek, jadi keping filternya diberi sebutan. */
        'kategori' => ['nama' => 'Kategori temuan', 'sebut' => 'Temuan', 'ket' => 'Klasifikasi temuan pada laporan pemeriksaan'],
        'sifat' => ['nama' => 'Sifat rekomendasi', 'sebut' => 'Sifat', 'ket' => 'Jenis kewajiban yang ditetapkan dalam rekomendasi'],
        'bentuk' => ['nama' => 'Bentuk tindak lanjut', 'ket' => 'Jenis tindakan untuk memenuhi rekomendasi', 'baris' => true],
    ];

    public const URUT_LAIN = ['intern', 'kategori', 'sifat', 'bentuk'];

    public const URUT_BPK = ['SS', 'BS', 'BT'];

    /* Pilihan filter SIPTL: status BPK, ditambah yang belum diunggah (antrean Setba, bukan status). */
    public const FILTER_BPK = ['SS', 'BS', 'BT', 'belumUnggah'];

    /* Warna grafik (lolos uji buta warna). "proses" abu, supaya yang menonjol hanya yang
       menunggu satuan kerja (kuning). */
    public const WARNA = [
        'M' => 'var(--d-ok)', 'BM' => 'var(--d-belum)',
        'SS' => 'var(--d-ok)', 'BS' => 'var(--d-belum)', 'BT' => 'var(--d-bt)', 'belumUnggah' => 'var(--d-proses)',
        'masuk' => 'var(--d-masuk)', 'tunggu' => 'var(--d-tunggu)', 'proses' => 'var(--d-proses)',
    ];

    /* Warna tahap posisi berkas, urut rantai posisi; tokennya di simtlhp-tambahan.css. */
    public const WARNA_MEJA = ['satker' => 'var(--d-tunggu)', 'setba' => 'var(--d-setba)', 'uki' => 'var(--d-uki)',
        'itjen' => 'var(--d-itjen)', 'selesai' => 'var(--d-ok)'];

    /* Warna tulisan di potongan berwarna mengikuti terang warnanya. */
    public const TULISAN_DI = ['M' => '#111827', 'SS' => '#111827', 'BT' => '#111827',
        'BM' => '#FFFFFF', 'BS' => '#FFFFFF', 'belumUnggah' => '#FFFFFF'];

    public const NAMA_BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
        'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    /* Meja = siapa yang sedang memegang berkasnya, dari pemegang posisi. */
    public const NAMA_MEJA = ['satker' => 'Satuan kerja', 'setba' => 'Setba', 'uki' => 'UKI',
        'itjen' => 'Inspektorat', 'selesai' => 'Selesai diperiksa'];

    /* Sebutan pendek untuk sumbu grafik di panel sempit. */
    public const PENDEK_MEJA = ['satker' => 'Satker', 'setba' => 'Setba', 'uki' => 'UKI',
        'itjen' => 'Itjen', 'selesai' => 'Selesai'];

    /* ---- Butir dan baris ---- */

    /** Rekomendasi yang terlihat → butir, urut seperti datanya. */
    public static function butir(Collection $rekomendasi): array
    {
        return $rekomendasi->map(function (Rekomendasi $r) {
            $semua = $r->daftarSasaran()->values();

            return ['rek' => $r, 'tem' => $r->temuan, 'lap' => $r->temuan->laporan,
                'semua' => $semua, 'baris' => $semua];
        })->values()->all();
    }

    /* Tab data di atas Dashboard: data historis (impor file pemantauan lama) dan data sistem
       dipantau terpisah. */
    public const DATA = [
        'semua' => ['nama' => 'Semua data', 'ikon' => 'Layers', 'ket' => 'Data historis dan data sistem'],
        'sistem' => ['nama' => 'Data sistem', 'ikon' => 'Monitor', 'ket' => 'Dicatat lewat Catat laporan baru'],
        'historis' => ['nama' => 'Data historis', 'ikon' => 'History', 'ket' => 'Diimpor dari file pemantauan lama'],
    ];

    /** Butir dipangkas ke baris tab data yang dipilih; seluruh halaman, termasuk acuan "dari N", ikut. */
    public static function dalamData(array $items, string $data): array
    {
        if ($data === 'semua') {
            return $items;
        }
        $historis = $data === 'historis';
        $keluar = [];
        foreach ($items as $x) {
            $baris = $x['semua']->filter(fn (Sasaran $b) => $b->historis() === $historis)->values();
            if ($baris->isNotEmpty()) {
                $keluar[] = array_merge($x, ['semua' => $baris, 'baris' => $baris]);
            }
        }

        return $keluar;
    }

    public static function meja(Sasaran $b): string
    {
        return match ($b->pos()->pemegang()) {
            PeranPengguna::SATKER => 'satker',
            PeranPengguna::SETBA => 'setba',
            PeranPengguna::UKI => 'uki',
            PeranPengguna::INSPEKTORAT => 'itjen',
            default => 'selesai',
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

    /** Angka SIPTL hanya dari yang sudah diunggah; yang lain bernilai nol di sisi ini. */
    public static function sisaBpk(Sasaran $b): int
    {
        return $b->statusSiptl() ? max(0, (int) $b->nilai - $b->nilaiDiakuiBpk()) : 0;
    }

    /** Status SIPTL baris; null bila Setba belum mencatat unggahannya (tidak dihitung, bukan BT). */
    public static function statusBpk(Sasaran $b): ?string
    {
        return $b->statusSiptl()?->value;
    }

    /** Sudah memadai dan menunggu Setba mencatat unggahannya ke SIPTL; bukan status BPK. */
    public static function belumUnggah(Sasaran $b, array $x): bool
    {
        return $b->perluUnggah($x['lap']->sumber);
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
            'intern' => $x['tem']->kategoriIntern?->nama ?? '',
            'kategori' => $x['tem']->kategori?->nama ?? '',
            'sifat' => $x['rek']->sifat?->nama ?? '',
            default => '',
        };
    }

    public static function tglMasuk(array $x): string
    {
        $t = $x['lap']->tgl_terima ?? $x['lap']->tgl_surat;

        return $t ? $t->toDateString() : '';
    }

    /* ---- Filter ---- */

    public static function adaFilter(array $f): bool
    {
        return $f['tahun'] || $f['satker'] || $f['hasil'] !== ''
            || $f['bpk'] || $f['meja'] || collect($f['lain'])->contains(fn ($v) => count($v) > 0);
    }

    /**
     * `kecuali`: filter yang dilewati. Filter per baris (satuan kerja, meja, bentuk, memadai,
     * SIPTL) memotong rekomendasi jadi bagian barisnya.
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
                /* Status BPK hanya ada pada LHP. */
                $lhp = self::lhp($x);
                $baris = $x['baris']->filter(function (Sasaran $b) use ($x, $satker, $meja, $bentuk, $hasil, $bpk, $lhp) {
                    return ($satker === null || isset($satker[self::satkerKunci($b)]))
                        && ($meja === null || isset($meja[self::meja($b)]))
                        && ($bentuk === null || isset($bentuk[self::bentuk($b)]))
                        && ($hasil === '' || (self::memadai($b) ? 'M' : 'BM') === $hasil)
                        && ($bpk === null || ($lhp && (isset($bpk[self::statusBpk($b) ?? ''])
                            || (isset($bpk['belumUnggah']) && self::belumUnggah($b, $x)))));
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

    /** Pilihan yang sudah tidak ada di daftarnya tidak ikut memfilter. */
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
            'tahun' => $cocok($f['tahun'], $sah['tahun']),
            'satker' => $cocok($f['satker'], $sah['satker']),
            'lain' => $lain,
        ]);
    }

    /* ---- Angka kartu ---- */

    /**
     * Angka kartu, per tindak lanjut satuan kerja: memadai/belum, meja yang belum memadai, nilai
     * dan sisa, status SIPTL (LHP yang sudah diunggah) dan yang belum diunggah.
     */
    public static function hitungTindak(array $K): array
    {
        $o = ['n' => 0, 'M' => 0, 'BM' => 0, 'nilai' => 0, 'sisa' => 0, 'meja' => [],
            'bpk' => ['n' => 0, 'SS' => 0, 'BS' => 0, 'BT' => 0], 'belumUnggah' => 0];
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
                    $m = self::meja($b);
                    $o['meja'][$m] = ($o['meja'][$m] ?? 0) + 1;
                }
                if ($lhp && ($st = self::statusBpk($b)) !== null) {
                    $o['bpk']['n']++;
                    $o['bpk'][$st]++;
                } elseif ($lhp && self::belumUnggah($b, $x)) {
                    $o['belumUnggah']++;
                }
            }
        }

        return $o + ['rek' => count($rek), 'lap' => count($lap), 'lapLhp' => count($lapLhp)];
    }

    /** Belum memadai yang menunggu tanggapan satuan kerja, per satuan kerja (terbanyak dulu). */
    /** Per satuan kerja: yang belum memadai menurut posisi berkasnya (`meja`), urut terbanyak. */
    public static function perProses(array $S): array
    {
        $peta = [];
        foreach ($S as $x) {
            foreach ($x['baris'] as $b) {
                if (self::memadai($b)) {
                    continue;
                }
                $k = self::satkerKunci($b);
                $peta[$k] ??= ['k' => $k, 'nama' => $b->satker?->nama ?? 'Tidak diisi', 'BM' => 0,
                    'meja' => array_fill_keys(array_keys(self::NAMA_MEJA), 0)];
                $peta[$k]['BM']++;
                $peta[$k]['meja'][self::meja($b)]++;
            }
        }
        $baris = array_values($peta);
        usort($baris, fn ($a, $b) => [$b['BM'], $b['meja']['satker']] <=> [$a['BM'], $a['meja']['satker']]
            ?: self::bandingNama($a['nama'], $b['nama']));

        return $baris;
    }

    /**
     * Per tahun laporan: memadai dan belum, serta status SIPTL untuk LHP (`siptl` = yang sudah
     * diunggah).
     */
    public static function perTahun(array $Y): array
    {
        $peta = [];
        foreach ($Y as $x) {
            $k = self::tahun($x);
            $peta[$k] ??= ['k' => $k, 'M' => 0, 'BM' => 0, 'nilai' => 0, 'sisa' => 0, 'lhp' => 0, 'siptl' => 0,
                'SS' => 0, 'BS' => 0, 'BT' => 0, 'belumUnggah' => 0, 'sisaBpk' => 0, 'tugas' => 0];
            $lhp = self::lhp($x);
            foreach ($x['baris'] as $b) {
                $peta[$k]['tugas']++;
                $peta[$k][self::memadai($b) ? 'M' : 'BM']++;
                $peta[$k]['nilai'] += self::nilai($b);
                $peta[$k]['sisa'] += self::sisa($b);
                if ($lhp) {
                    $peta[$k]['lhp']++;
                    $peta[$k]['sisaBpk'] += self::sisaBpk($b);
                    if (($st = self::statusBpk($b)) !== null) {
                        $peta[$k]['siptl']++;
                        $peta[$k][$st]++;
                    } elseif (self::belumUnggah($b, $x)) {
                        $peta[$k]['belumUnggah']++;
                    }
                }
            }
        }

        return $peta;
    }

    /**
     * Per satuan kerja, urut tumpukan belum memadai terbanyak; sisa nilai dua versi (SIPTL hanya
     * LHP).
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
                    'M' => 0, 'BM' => 0, 'sisa' => 0, 'siptl' => 0, 'sisaBpk' => 0, 'nilai' => 0, 'nRek' => 0,
                    'SS' => 0, 'BS' => 0, 'BT' => 0, 'belumUnggah' => 0];
                $rekPer[$k][$x['rek']->id] = true;
                $peta[$k][self::memadai($b) ? 'M' : 'BM']++;
                $peta[$k]['nilai'] += self::nilai($b);
                $peta[$k]['sisa'] += self::sisa($b);
                if ($lhp && ($st = self::statusBpk($b)) !== null) {
                    $peta[$k]['siptl']++;
                    $peta[$k]['sisaBpk'] += self::sisaBpk($b);
                    $peta[$k][$st]++;
                } elseif ($lhp && self::belumUnggah($b, $x)) {
                    $peta[$k]['belumUnggah']++;
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

    /** Rekomendasi di balik satu sel tabel keseluruhan — aturan sama dengan `perSatker`. */
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
                    $kolom === 'M' => self::memadai($b),
                    $kolom === 'BM' => ! self::memadai($b),
                    $kolom === 'siptl' => $lhp && self::statusBpk($b) !== null,
                    $kolom === 'belumUnggah' => $lhp && self::belumUnggah($b, $x),
                    in_array($kolom, self::URUT_BPK, true) => $lhp && self::statusBpk($b) === $kolom,
                    $kolom === 'nilai' => self::nilai($b),
                    $kolom === 'sisa' => self::sisa($b),
                    $kolom === 'sisaBpk' => $lhp ? self::sisaBpk($b) : 0,
                    default => 1,
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
     * Versi BPSDM (Inspektorat) dan versi SIPTL (BPK) berdampingan; SIPTL hanya LHP yang sudah
     * diunggah, yang belum diunggah dihitung terpisah.
     */
    public static function hitungBanding(array $S): array
    {
        $unor = ['M' => ['n' => 0, 'rp' => 0], 'BM' => ['n' => 0, 'rp' => 0]];
        $bpk = array_fill_keys(self::URUT_BPK, ['n' => 0, 'rp' => 0]);
        $belum = ['n' => 0, 'rp' => 0];
        $n = $nLhp = $nSiptl = $beda = $mSiptl = $mLha = 0;
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
                    $mLha += (int) $ok;
                    continue;
                }
                $nLhp++;
                $st = self::statusBpk($b);
                if ($st === null) {
                    if (self::belumUnggah($b, $x)) {
                        $belum['n']++;
                        $belum['rp'] += $nilai;
                    }
                    continue;
                }
                $nSiptl++;
                $mSiptl += (int) $ok;
                $bpk[$st]['n']++;
                $sisaB = self::sisaBpk($b);
                $bpk['SS']['rp'] += $nilai - $sisaB;
                if ($st !== 'SS') {
                    $bpk[$st]['rp'] += $sisaB;
                }
                if ($ok && $st !== 'SS') {
                    $beda++;
                }
            }
        }

        /* Asal yang memadai: sudah di SIPTL (`mSiptl`), belum diunggah, atau LHA (`mLha`). */
        return ['adaLhp' => $nLhp > 0, 'n' => $n, 'nSiptl' => $nSiptl, 'unor' => $unor, 'bpk' => $bpk, 'beda' => $beda,
            'belumUnggah' => $belum, 'mSiptl' => $mSiptl, 'mLha' => $mLha];
    }

    /** Per kategori internal, urut tumpukan belum memadai terbanyak. */
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

    /* ---- Waktu ---- */

    /** Dua belas bulan terakhir sampai bulan ini; `akhir` = tanggal penutup bulan. */
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

    /** Putusan Inspektorat atas satu baris, urut tanggal. */
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

    /** Tanggal tindak lanjut dinyatakan memadai: putusan terakhir Inspektorat atas baris itu. */
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


    /** Lima belum memadai dengan sisa nilai terbesar. */
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

    /** Pembuka baku uraian dibuang dari tampilan saja. */
    public static function pokokUraian(?string $t): string
    {
        $t = (string) $t;
        $s = preg_replace('/^(?:Menteri Pekerjaan Umum(?: dan Perumahan Rakyat)?|Kepala BPSDM) agar (?:memerintahkan Kepala BPSDM (?:untuk )?)?/u', '', $t);

        return $s === $t || $s === '' ? $t : mb_strtoupper(mb_substr($s, 0, 1)).mb_substr($s, 1);
    }

    /* ---- Isi filter ---- */

    public static function daftarTahun(array $items): array
    {
        $t = array_values(array_unique(array_map(fn ($x) => self::tahun($x), $items)));
        sort($t, SORT_STRING);

        return $t;
    }

    /**
     * Satuan kerja untuk filter, mengikuti data master: balai wilayah disebut kotanya; unit
     * nonaktif tetap ditawarkan selama masih punya data.
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

    /** Pilihan filter tambahan dari data master; nilai lama di data tetap ditawarkan. */
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

    /** Jumlah tiap pilihan sebuah filter, dihitung dengan filter lain. */
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

    /** Skala sumbu: kelipatan 1, 2, atau 5, kira-kira tiga garis. */
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
     * Pembanding nama memakai Collator (ICU); strnatcasecmp melompati spasi sehingga urutannya
     * salah.
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
