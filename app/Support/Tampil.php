<?php

namespace App\Support;

use Carbon\Carbon;

/** Pembantu tampilan. Tidak ada aturan bisnis di sini — hanya cara menuliskan. */
class Tampil
{
    /** "Rp 18.600.000", atau "—" untuk nol. */
    public static function rupiah($n): string
    {
        $n = (int) $n;

        return $n ? 'Rp '.number_format($n, 0, ',', '.') : '—';
    }

    /** "Rp 1.5 M", "Rp 24 jt". */
    public static function rupiahSingkat($n): string
    {
        $n = (int) $n;
        if (! $n) {
            return 'Rp 0';
        }
        if ($n >= 1e9) {
            return 'Rp '.str_replace('.0', '', number_format($n / 1e9, 1, '.', '')).' M';
        }
        if ($n >= 1e6) {
            return 'Rp '.round($n / 1e6).' jt';
        }

        return self::rupiah($n);
    }

    /**
     * Tanggal (Carbon atau "2026-08-05"). Nama bulan ditulis sendiri: Carbon menyingkat Agustus
     * "Agt", di sini "Agu".
     */
    public static function tgl($t): string
    {
        if (! $t) {
            return '—';
        }
        if (is_string($t)) {
            $t = Carbon::parse($t);
        }

        return $t->format('d').' '.self::BULAN[(int) $t->format('n') - 1].' '.$t->format('Y');
    }

    /** "17 Agu 2026 · 09.14" untuk log; baris bertanggal saja (jam 00.00.00) tanpa jam. */
    public static function waktuLog($t): string
    {
        if (! $t) {
            return '—';
        }
        $t = is_string($t) ? Carbon::parse($t) : $t;

        return $t->format('H:i:s') === '00:00:00' ? self::tgl($t) : self::tgl($t).' · '.$t->format('H.i');
    }

    /** "17 Agu 2026, 09.14": kapan seseorang mencatat atau mengirim. */
    public static function tglJam($t): string
    {
        if (! $t) {
            return '—';
        }
        $t = is_string($t) ? Carbon::parse($t) : $t;

        return self::tgl($t).', '.$t->format('H.i');
    }

    /** "baru saja", "5 menit lalu", "2 jam lalu", lalu tanggal dan jamnya. */
    public static function sejak($t): string
    {
        if (! $t) {
            return '—';
        }
        $t = is_string($t) ? Carbon::parse($t) : $t;

        return self::lalu((int) floor($t->diffInSeconds(now(), false)), $t);
    }

    /**
     * Sama dengan sejak(), dari selisih detik; tanpa `$t` yang lebih dari sehari ditulis "N hari
     * lalu".
     */
    public static function lalu(int $detik, $t = null): string
    {
        $menit = intdiv(max(0, $detik), 60);

        return match (true) {
            $menit < 2  => 'baru saja',
            $menit < 60 => $menit.' menit lalu',
            $menit < 1440 && (! $t || $t->isSameDay(now())) => intdiv($menit, 60).' jam lalu',
            (bool) $t   => self::waktuLog($t),
            default     => intdiv($menit, 1440).' hari lalu',
        };
    }

    private const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
                           'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** Lama keterlambatan dalam satuan yang mudah dibayangkan (bukan "lewat 3665 hari"). */
    public static function lamaTelat(int $n): string
    {
        if ($n < 31) {
            return "lewat {$n} hari";
        }
        if ($n < 365) {
            return 'lewat '.round($n / 30).' bulan';
        }
        $th = $n / 365;

        return 'lewat '.($th < 2 ? 'setahun' : floor($th).' tahun');
    }

    /** Nama pendek sederet satuan kerja. */
    public static function daftarPendek($satker): string
    {
        return collect($satker)->map(fn ($s) => $s->namaPendek())->join(', ');
    }

    /** Persentase bulat untuk bagian dari seluruhnya. */
    public static function bagian($a, $b): string
    {
        return $b > 0 ? round($a / $b * 100).'%' : '0%';
    }
}
