<?php

use App\Enums\JenisReferensi;
use App\Models\Referensi;
use Illuminate\Database\Migrations\Migration;

/** Mengisi daftar sifat rekomendasi untuk basis data yang sudah berjalan. */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'Administratif',
            'Informasi kerugian negara',
        ] as $i => $nama) {
            Referensi::firstOrCreate(
                ['jenis' => JenisReferensi::SIFAT_REKOM->value, 'nama' => $nama],
                ['urutan' => $i + 1, 'perlu_nilai' => $nama === 'Informasi kerugian negara'],
            );
        }
    }

    /** Tidak dibalik: membuangnya mengosongkan `sifat_id` yang sudah dipilih. */
    public function down(): void {}
};
