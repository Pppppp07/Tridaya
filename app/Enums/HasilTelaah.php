<?php

namespace App\Enums;

/**
 * Penilaian Itjen, terpisah dari StatusTindakLanjut (BPK). Sebutannya mengikuti jenis laporan: LHA
 * Sesuai/Belum sesuai, LHP Memadai/Belum memadai. NULL = belum ditelaah, bukan belum memadai.
 */
enum HasilTelaah: string
{
    case M  = 'M';
    case BM = 'BM';

    public function nama(?SumberLaporan $sumber = null): string
    {
        $lha = $sumber === SumberLaporan::LHA;

        return match ($this) {
            self::M  => $lha ? 'Sesuai' : 'Memadai',
            self::BM => $lha ? 'Belum sesuai' : 'Belum memadai',
        };
    }

    public function memadai(): bool
    {
        return $this === self::M;
    }

    /** Kelas lencana; sejalan dengan StatusTindakLanjut supaya sewarna. */
    public function cap(): string
    {
        return $this === self::M ? 'cap-ss' : 'cap-bs';
    }

    /** Singkatan: LHP M/BM, LHA S/BS. "SS" milik BPK (Sudah sesuai). */
    public function kode(?SumberLaporan $sumber = null): string
    {
        if ($sumber === SumberLaporan::LHA) {
            return $this === self::M ? 'S' : 'BS';
        }

        return $this->value;
    }
}
