<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Tanggal dan catatan turun dari rekomendasi ke tiap bentuk tindak lanjut. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tindakans', function (Blueprint $t) {
            /* Rencana aksi bentuk ini; kosong = ikut tanggal rekomendasinya. */
            $t->date('tgl_renaksi')->nullable()->after('urutan');
            $t->date('target_selesai')->nullable()->after('tgl_renaksi');
            $t->text('catatan')->nullable()->after('target_selesai');
        });

        /* Data lama diisi dari rekomendasinya (dulu satu tindakan per rekomendasi). */
        foreach (DB::table('rekomendasis')->select('id', 'tenggat_jawab', 'target_selesai')
            ->orderBy('id')->cursor() as $r) {
            DB::table('tindakans')
                ->where('rekomendasi_id', $r->id)
                ->update([
                    'tgl_renaksi'    => $r->tenggat_jawab,
                    'target_selesai' => $r->target_selesai,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('tindakans', function (Blueprint $t) {
            $t->dropColumn(['tgl_renaksi', 'target_selesai', 'catatan']);
        });
    }
};
