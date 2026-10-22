<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hanya bertambah; tidak ada jalur ubah maupun hapus.
        Schema::create('riwayat_berkas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekomendasi_id')->constrained('rekomendasis')->cascadeOnDelete();
            $table->timestamp('waktu');
            $table->foreignId('aktor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label_aktor');
            $table->text('aksi');
            $table->string('posisi_dari', 20)->nullable();
            $table->string('posisi_ke', 20)->nullable();
            $table->string('status_dari', 2)->nullable();
            $table->string('status_ke', 2)->nullable();
            $table->timestamps();
            $table->index(['rekomendasi_id', 'waktu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_berkas');
    }
};
