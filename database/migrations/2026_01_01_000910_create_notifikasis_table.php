<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifikasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rekomendasi_id')->constrained('rekomendasis')->cascadeOnDelete();
            $table->timestamp('waktu');
            $table->string('label_pelaku');
            $table->text('aksi');
            $table->json('untuk_peran');   // peran yang berwenang atau berkepentingan

            // Bagian halaman rincian yang berubah karena tindakan ini.
            $table->string('blok', 30)->nullable();
            $table->foreignId('satker_id')->nullable()->constrained('satkers')->nullOnDelete();
            $table->timestamps();
            $table->index('waktu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasis');
    }
};
