<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hasil telaah UKI dan verifikasi Inspektorat, terpisah dari tindak lanjut satuan kerja.
        Schema::create('telaahs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekomendasi_id')->constrained('rekomendasis')->cascadeOnDelete();
            $table->date('tanggal');
            $table->foreignId('oleh_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label_oleh');          // "UKI" atau "Inspektorat"
            $table->text('catatan');               // wajib — telaah tanpa kesimpulan bukan telaah

            // Penunjuk berkas catatan telaah (berkasnya di lampiran).
            $table->foreignId('lampiran_id')->nullable()->constrained('lampirans')->nullOnDelete();

            $table->timestamps();
            $table->index(['rekomendasi_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telaahs');
    }
};
