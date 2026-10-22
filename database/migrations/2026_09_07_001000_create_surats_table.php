<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Surat pengantar antar unit (Setba → UKI → Setba → Inspektorat). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekomendasi_id')->constrained('rekomendasis')->cascadeOnDelete();
            $table->foreignId('sasaran_id')->nullable()->constrained('sasarans')->nullOnDelete();
            $table->string('dari', 20);
            $table->string('ke', 20);
            $table->string('nomor');
            $table->date('tanggal');
            $table->date('tanggal_catat')->nullable();
            $table->string('perihal', 500)->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('lampiran_id')->nullable()->constrained('lampirans')->nullOnDelete();
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['rekomendasi_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surats');
    }
};
