<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pengembalian dicatat tersendiri supaya alasannya mudah ditemukan.
        Schema::create('pengembalians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekomendasi_id')->constrained('rekomendasis')->cascadeOnDelete();
            $table->date('tanggal');
            $table->foreignId('oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label_oleh');          // "Setba", "UKI", "Inspektorat"
            $table->text('alasan');                // wajib — pengembalian tanpa alasan memaksa menebak
            $table->timestamps();
            $table->index(['rekomendasi_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengembalians');
    }
};
