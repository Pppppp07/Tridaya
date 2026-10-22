<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Telaah punya hasil dan suratnya sendiri. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telaahs', function (Blueprint $table) {
            $table->foreignId('sasaran_id')->nullable()->after('rekomendasi_id')
                  ->constrained('sasarans')->nullOnDelete();

            // M / BM. Sumbu Itjen, bukan sumbu BPK — jangan diisi SS/BS/TD.
            $table->string('hasil', 2)->nullable()->after('label_oleh');

            $table->string('nomor_surat')->nullable()->after('hasil');
            $table->date('tgl_surat')->nullable()->after('nomor_surat');
            $table->string('perihal')->nullable()->after('tgl_surat');

            $table->index('sasaran_id');
        });
    }

    public function down(): void
    {
        Schema::table('telaahs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sasaran_id');
            $table->dropColumn(['hasil', 'nomor_surat', 'tgl_surat', 'perihal']);
        });
    }
};
