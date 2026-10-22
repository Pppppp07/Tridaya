<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Berkas jadi tautan, bukan unggahan. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lampirans', function (Blueprint $table) {
            $table->string('tautan', 500)->nullable()->after('jenis_dokumen_id');

            // Kosong = berkas milik rekomendasi atau laporan seutuhnya.
            $table->foreignId('sasaran_id')->nullable()->after('rekomendasi_id')
                  ->constrained('sasarans')->nullOnDelete();
            $table->index('sasaran_id');
        });
    }

    public function down(): void
    {
        Schema::table('lampirans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sasaran_id');
            $table->dropColumn('tautan');
        });
    }
};
