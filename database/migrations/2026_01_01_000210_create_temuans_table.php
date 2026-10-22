<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temuans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->constrained('laporans')->cascadeOnDelete();

            // Satuan kerja tempat temuan terjadi (bukan penanggung jawab rekomendasi).
            $table->foreignId('satker_id')->nullable()->constrained('satkers')->nullOnDelete();

            $table->string('kode', 30)->unique();
            $table->string('nomor_pada_surat', 30)->nullable(); // diketik manual, ikut surat asli
            $table->string('judul');
            $table->text('kondisi')->nullable();
            $table->text('kriteria')->nullable();
            $table->text('sebab')->nullable();
            $table->text('akibat')->nullable();
            $table->foreignId('kategori_temuan_id')->nullable()->constrained('kategori_temuans')->nullOnDelete();
            $table->foreignId('kategori_intern_id')->nullable()->constrained('referensis')->nullOnDelete();
            $table->unsignedBigInteger('nilai')->default(0);
            $table->timestamps();
            $table->index('satker_id');
        });
        // Sama seperti laporan: tidak ada kolom status maupun posisi di sini.
    }

    public function down(): void
    {
        Schema::dropIfExists('temuans');
    }
};
