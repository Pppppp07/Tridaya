<?php

namespace App\Support;

use App\Enums\HasilTelaah;
use App\Enums\PeranPengguna;
use App\Models\Rekomendasi;

/**
 * Kemajuan tindak lanjut sebuah rekomendasi (kolom "Kemajuan" dan kartu "Posisi tindak lanjut"):
 * berapa yang selesai dan rangkuman yang dihitung.
 */
class TindakLanjutRingkas
{
    /** Sebutan meja yang cukup pendek untuk keping. */
    public const MEJA_SINGKAT = ['Satuan kerja' => 'Satker', 'BPK / SIPTL' => 'BPK'];

    public const URUT_MEJA = ['Satker', 'Setba', 'UKI', 'Inspektorat', 'BPK', 'Selesai'];

    public const KET_SELESAI = 'LHP selesai setelah verifikasi internal selesai dan berstatus SS di SIPTL. LHA selesai setelah dinyatakan sesuai oleh Inspektorat.';

    /**
     * Dihitung per Satker bila pembacanya melihat lebih dari satu Satker (pusat, pimpinan beberapa
     * Satker); per tindak lanjut bila cakupannya satu Satker.
     */
    public static function perSatker(PeranPengguna $peran, int|array|null $satkerId): bool
    {
        return ! $peran->terbatasSatker() || is_array($satkerId);
    }

    /** Keterangan kolom Kemajuan; akun satuan kerja menghitung tindak lanjutnya sendiri. */
    public static function keterangan(PeranPengguna $peran, int|array|null $satkerId = null): array
    {
        return [
            match (true) {
                ! self::perSatker($peran, $satkerId) => 'Jumlah tindak lanjut Satker Anda yang selesai dibandingkan dengan seluruh tindak lanjut Satker Anda pada rekomendasi ini.',
                $peran->terbatasSatker() => 'Jumlah Satker yang Anda pimpin yang selesai dibandingkan dengan seluruh Satker yang Anda pimpin pada rekomendasi ini. Satker dihitung selesai bila semua tindak lanjutnya selesai.',
                default => 'Jumlah Satker yang selesai dibandingkan dengan seluruh Satker pada rekomendasi ini. Satker dihitung selesai bila semua tindak lanjutnya selesai.',
            },
            self::KET_SELESAI,
        ];
    }

    /**
     * Satu butir per satuan kerja dan tindak lanjut yang terlihat: mejanya dan selesai atau belum.
     */
    public static function daftar(Rekomendasi $r, PeranPengguna $peran, int|array|null $satkerId): array
    {
        $jenis = $r->jenis();

        return $r->barisTerlihat($peran, $satkerId)->map(function ($x) use ($jenis) {
            $info = $x->presentasi($jenis);

            return [
                'key'     => $x->satker_id.'|'.$x->tindakan_id,
                'satker'  => $x->satker_id,
                'meja'    => self::MEJA_SINGKAT[$info['pemegang']] ?? $info['pemegang'],
                'selesai' => $info['selesai'],
            ];
        })->values()->all();
    }

    /**
     * Jumlah selesai dan rangkumannya. LHP: status SIPTL paling belakang, null bila belum ada yang di
     * SIPTL; LHA "Sesuai" hanya bila semua baris sesuai.
     */
    public static function kemajuan(Rekomendasi $r, PeranPengguna $peran, int|array|null $satkerId): array
    {
        $daftar = self::daftar($r, $peran, $satkerId);
        $baris = $r->barisTerlihat($peran, $satkerId);

        if ($r->jenis()->melewatiSiptl()) {
            $rangkuman = Rekomendasi::rangkumBpk($baris->map->statusSiptl())?->value;
        } else {
            $rangkuman = $baris->isNotEmpty() && $baris->every(fn ($b) => $b->hasil === HasilTelaah::M) ? 'M' : 'BM';
        }

        /* Pembaca lintas satuan kerja menghitung per Satker: selesai bila semua tindak lanjutnya selesai. */
        $perSatker = self::perSatker($peran, $satkerId);
        if ($perSatker) {
            $daftar = collect($daftar)->groupBy('satker')
                ->map(fn ($g) => ['selesai' => $g->every(fn ($x) => $x['selesai'])])->values()->all();
        }

        return [
            'selesai'   => count(array_filter($daftar, fn ($x) => $x['selesai'])),
            'dari'      => count($daftar),
            'satuan'    => $perSatker ? 'Satker' : 'tindak lanjut',
            'rangkuman' => $rangkuman,
        ];
    }

    /** Bagian yang sudah selesai, untuk urutan kolom Kemajuan. */
    public static function bagian(Rekomendasi $r, PeranPengguna $peran, int|array|null $satkerId): float
    {
        $k = self::kemajuan($r, $peran, $satkerId);

        return $k['dari'] ? $k['selesai'] / $k['dari'] : 0;
    }
}
