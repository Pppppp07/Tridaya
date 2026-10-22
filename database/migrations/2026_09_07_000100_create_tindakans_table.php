<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bentuk tindak lanjut yang dituntut rekomendasi (satu rekomendasi bisa lebih dari satu bentuk).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tindakans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekomendasi_id')->constrained('rekomendasis')->cascadeOnDelete();
            $table->foreignId('bentuk_id')->nullable()->constrained('referensis')->nullOnDelete();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();
            $table->index('rekomendasi_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tindakans');
    }
};
