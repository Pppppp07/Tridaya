<?php

use App\Enums\JenisReferensi;
use App\Models\Referensi;
use App\Support\BentukTindakLanjut;
use Illuminate\Database\Migrations\Migration;

/** Daftar bentuk tindak lanjut disamakan dengan SOP. */
return new class extends Migration
{
    public function up(): void
    {
        $urutan = array_flip(BentukTindakLanjut::nama());

        foreach (BentukTindakLanjut::petaLama() as $lama => $baru) {
            $baris = Referensi::where('jenis', JenisReferensi::BENTUK_TL->value)
                ->where('nama', $lama)->first();

            if (! $baris) {
                continue;
            }

            if ($baru === null) {
                /* Tidak dihapus (masih dipakai) — dinonaktifkan saja. */
                $baris->update(['aktif' => false]);
                continue;
            }

            $baris->update(['nama' => $baru, 'urutan' => $urutan[$baru] + 1, 'aktif' => true]);
        }

        /* firstOrCreate supaya aman dijalankan ulang. */
        foreach (BentukTindakLanjut::nama() as $i => $nama) {
            Referensi::firstOrCreate(
                ['jenis' => JenisReferensi::BENTUK_TL->value, 'nama' => $nama],
                ['urutan' => $i + 1, 'aktif' => true],
            );
        }
    }

    /** Tidak dibalik. */
    public function down(): void {}
};
