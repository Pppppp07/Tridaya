<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Permintaan perubahan menyebut baris satuan kerja yang dimaksud. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permintaan_ubahs', function (Blueprint $t) {
            $t->foreignId('sasaran_id')->nullable()->after('rekomendasi_id')
                ->constrained('sasarans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('permintaan_ubahs', function (Blueprint $t) {
            $t->dropConstrainedForeignId('sasaran_id');
        });
    }
};
