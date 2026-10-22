<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Daftarnya berbeda antara LHA dan LHP (pilihan bertingkat).
        Schema::create('kategori_temuans', function (Blueprint $table) {
            $table->id();
            $table->string('sumber', 3); // LHA atau LHP
            $table->string('nama');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->index(['sumber', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kategori_temuans');
    }
};
