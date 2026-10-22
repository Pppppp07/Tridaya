<?php

use App\Enums\JenisReferensi;
use App\Support\BentukTindakLanjut;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Dokumen usulan tiap bentuk tindak lanjut pindah ke data master supaya bisa diatur Admin DTI. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referensis', function (Blueprint $table) {
            $table->json('dokumen')->nullable()->after('nama');
        });

        foreach (BentukTindakLanjut::peta() as $nama => $dokumen) {
            DB::table('referensis')->where('jenis', JenisReferensi::BENTUK_TL->value)
                ->where('nama', $nama)->whereNull('dokumen')
                ->update(['dokumen' => json_encode($dokumen, JSON_UNESCAPED_UNICODE)]);
        }
    }

    public function down(): void
    {
        Schema::table('referensis', function (Blueprint $table) {
            $table->dropColumn('dokumen');
        });
    }
};
