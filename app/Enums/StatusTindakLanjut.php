<?php

namespace App\Enums;

/* Status BPK di SIPTL. TD (tidak dapat ditindaklanjuti) sengaja tidak ada: BPSDM tidak memakainya. */
enum StatusTindakLanjut: string
{
    case BT = 'BT';
    case SS = 'SS';
    case BS = 'BS';

    public function nama(): string
    {
        return match ($this) {
            self::BT => 'Belum ditindaklanjuti',
            self::SS => 'Sesuai dengan rekomendasi',
            self::BS => 'Belum sesuai dengan rekomendasi',
        };
    }

    /** Sebutan lencana tanpa "dengan rekomendasi". */
    public function pendek(): string
    {
        return match ($this) {
            self::BT => 'Belum ditindaklanjuti',
            self::SS => 'Sudah sesuai',
            self::BS => 'Belum sesuai',
        };
    }

    public function cap(): string
    {
        return 'cap-'.strtolower($this->value);
    }

    /** BS memulangkan berkas ke satuan kerja dengan tenggat baru. */
    public function perluTenggatBaru(): bool
    {
        return $this === self::BS;
    }
}
