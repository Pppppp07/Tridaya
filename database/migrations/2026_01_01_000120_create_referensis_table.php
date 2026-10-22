<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu tabel untuk semua daftar pilihan datar.
        Schema::create('referensis', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 30);
            $table->string('nama');
            $table->string('keterangan')->nullable();
            $table->boolean('perlu_nilai')->default(false); // untuk bentuk tindak lanjut
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->index(['jenis', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referensis');
    }
};
