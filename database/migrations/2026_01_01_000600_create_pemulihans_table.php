<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tiap baris satu peristiwa pemulihan; nilai terkumpul selalu dijumlahkan dari sini.
        Schema::create('pemulihans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekomendasi_id')->constrained('rekomendasis')->cascadeOnDelete();
            $table->string('jenis', 20);              // setor atau perbaikan
            $table->date('tanggal');
            $table->unsignedBigInteger('nilai');

            // bukti untuk setoran tunai
            $table->string('no_ssbp')->nullable();
            $table->string('ntpn', 32)->nullable();
            $table->string('no_nota_kppn')->nullable();

            // bukti untuk pengurangan tanpa uang masuk
            $table->string('no_berita_acara')->nullable();

            $table->foreignId('lampiran_id')->nullable()->constrained('lampirans')->nullOnDelete();
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('rekomendasi_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemulihans');
    }
};
