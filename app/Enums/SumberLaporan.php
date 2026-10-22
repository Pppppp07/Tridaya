<?php

namespace App\Enums;

/* Sumber laporan menentukan kategori temuan, cara tenggat dihitung, dan panjang jalur. */
enum SumberLaporan: string
{
    case LHA = 'LHA';
    case LHP = 'LHP';

    public function nama(): string
    {
        return match ($this) {
            self::LHA => 'Laporan Hasil Audit',
            self::LHP => 'Laporan Hasil Pemeriksaan',
        };
    }

    public function penerbit(): string
    {
        return match ($this) {
            self::LHA => 'Inspektorat',
            self::LHP => 'BPK',
        };
    }

    /** Jumlah hari untuk menyampaikan jawaban atas rekomendasi. */
    public function hariTenggat(): int
    {
        return match ($this) {
            self::LHA => 30,
            self::LHP => 60,
        };
    }

    /** LHA memakai hari kerja, LHP memakai hari kalender. */
    public function pakaiHariKerja(): bool
    {
        return $this === self::LHA;
    }

    /** Hanya jalur LHP yang berlanjut ke SIPTL dan penilaian BPK. */
    public function melewatiSiptl(): bool
    {
        return $this === self::LHP;
    }

    public function dasarHukum(): string
    {
        return match ($this) {
            self::LHA => 'ketentuan pengawasan intern',
            self::LHP => 'UU 15/2004 Pasal 20',
        };
    }

    /** Satuan hitungan tenggatnya — LHA memakai hari kerja, LHP hari kalender. */
    public function satuanTenggat(): string
    {
        return $this->pakaiHariKerja() ? 'hari kerja' : 'hari kalender';
    }
}
