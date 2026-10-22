<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keputusan surat atas satu rekomendasi; bisa berulang tiap periode.
        Schema::create('keputusan_verifikasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verifikasi_id')->constrained('verifikasis')->cascadeOnDelete();
            $table->foreignId('rekomendasi_id')->constrained('rekomendasis')->cascadeOnDelete();
            $table->string('hasil', 2);                  // SS, BS, atau TD
            $table->date('tenggat_baru')->nullable();    // wajib bila hasilnya BS
            $table->foreignId('alasan_td_id')->nullable()->constrained('referensis')->nullOnDelete();
            $table->text('catatan')->nullable();
            // dicatat saat suratnya menyatakan sesuai padahal masih ada
            // kewajiban terbuka — diakui sadar, bukan lolos diam-diam
            $table->boolean('diakui_masih_terbuka')->default(false);
            $table->timestamps();
            $table->unique(['verifikasi_id', 'rekomendasi_id'], 'verifikasi_rekomendasi_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keputusan_verifikasis');
    }
};
