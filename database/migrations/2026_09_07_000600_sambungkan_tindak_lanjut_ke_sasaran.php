<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Lima tabel tindak lanjut pindah gantungan dari rekomendasi ke sasaran. */
return new class extends Migration
{
    private const TABEL = [
        'tindak_lanjuts', 'pemulihans', 'permintaan_dokumens',
        'pengembalians', 'riwayat_berkas',
    ];

    public function up(): void
    {
        foreach (self::TABEL as $nama) {
            if (Schema::hasColumn($nama, 'sasaran_id')) {
                continue;
            }
            Schema::table($nama, function (Blueprint $table) {
                $table->foreignId('sasaran_id')->nullable()->after('rekomendasi_id')
                      ->constrained('sasarans')->nullOnDelete();
                $table->index('sasaran_id');
            });
        }

        // Data lama disambungkan ke sasaran pertama rekomendasinya (dulu hanya satu satuan kerja).
        $sasaranPertama = DB::table('sasarans')
            ->join('tindakans', 'sasarans.tindakan_id', '=', 'tindakans.id')
            ->select('tindakans.rekomendasi_id', DB::raw('MIN(sasarans.id) as sasaran_id'))
            ->groupBy('tindakans.rekomendasi_id')
            ->pluck('sasaran_id', 'rekomendasi_id');

        foreach (self::TABEL as $nama) {
            foreach ($sasaranPertama as $rekId => $sasaranId) {
                DB::table($nama)
                    ->where('rekomendasi_id', $rekId)
                    ->whereNull('sasaran_id')
                    ->update(['sasaran_id' => $sasaranId]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $nama) {
            if (! Schema::hasColumn($nama, 'sasaran_id')) {
                continue;
            }
            Schema::table($nama, function (Blueprint $table) {
                $table->dropConstrainedForeignId('sasaran_id');
            });
        }
    }
};
