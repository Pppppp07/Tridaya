<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Surat verifikasi; satu nomor bisa memuat keputusan beberapa rekomendasi.
        Schema::create('verifikasis', function (Blueprint $table) {
            $table->id();
            $table->string('periode', 40);            // Triwulan I 2026, Semester II 2026
            $table->string('nomor_surat')->unique();
            $table->date('tgl_surat');
            $table->string('pejabat');
            $table->foreignId('lampiran_id')->nullable()->constrained('lampirans')->nullOnDelete();
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifikasis');
    }
};
