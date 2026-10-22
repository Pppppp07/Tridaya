<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lampirans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_id')->nullable()->constrained('laporans')->cascadeOnDelete();
            $table->foreignId('rekomendasi_id')->nullable()->constrained('rekomendasis')->cascadeOnDelete();
            $table->foreignId('jenis_dokumen_id')->nullable()->constrained('referensis')->nullOnDelete();

            $table->string('nama_asli')->nullable();   // dikosongkan saat berkas ditarik
            $table->string('nama_simpan')->nullable(); // nama acak di penyimpanan
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('ukuran')->nullable();
            $table->foreignId('diunggah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diunggah_pada')->nullable();

            // penarikan berkas: isinya dihapus, catatannya tetap
            $table->timestamp('ditarik_pada')->nullable();
            $table->foreignId('ditarik_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sebab_tarik', 20)->nullable();
            // Sebab hanya golongan; yang membaca perlu tahu duduk perkaranya.
            $table->text('catatan_tarik')->nullable();

            $table->timestamps();
            $table->index('rekomendasi_id');
        });
        // Kolom ini hanya menyimpan alamat berkas, bukan isinya.
    }

    public function down(): void
    {
        Schema::dropIfExists('lampirans');
    }
};
