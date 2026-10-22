<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu satuan kerja pada satu bentuk tindak lanjut — `posisi` menyimpan sampai mana berkas satuan
 * kerja ini berjalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sasarans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tindakan_id')->constrained('tindakans')->cascadeOnDelete();
            $table->foreignId('satker_id')->constrained('satkers')->cascadeOnDelete();
            $table->unsignedBigInteger('nilai')->default(0);
            $table->string('posisi', 20)->default('satker');
            $table->string('hasil', 2)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->unique(['tindakan_id', 'satker_id'], 'sasaran_tindakan_satker_unik');
            $table->index(['satker_id', 'posisi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sasarans');
    }
};
