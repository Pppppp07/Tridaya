<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Berkas yang sudah dikirim tidak bisa diubah sendiri; yang memutuskan pemegang berkas saat
        // ini.
        Schema::create('permintaan_ubahs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekomendasi_id')->constrained('rekomendasis')->cascadeOnDelete();
            $table->string('jenis', 20);
            $table->text('alasan');
            $table->foreignId('lampiran_sasaran_id')->nullable()->constrained('lampirans')->nullOnDelete();

            $table->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label_pengaju')->nullable();
            $table->date('tanggal');

            $table->string('status', 20)->default('menunggu');
            $table->foreignId('diputus_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label_pemutus')->nullable();
            $table->date('tgl_putus')->nullable();
            $table->text('catatan_putus')->nullable();

            $table->timestamps();
            $table->index(['rekomendasi_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permintaan_ubahs');
    }
};
