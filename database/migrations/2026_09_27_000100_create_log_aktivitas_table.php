<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Log aktivitas: hanya bertambah; yang mengurangi hanya pemangkasan masa simpan. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_aktivitas', function (Blueprint $t) {
            $t->id();
            $t->dateTime('waktu')->index();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('nama', 150)->nullable();
            $t->string('peran', 20)->nullable()->index();
            $t->foreignId('satker_id')->nullable()->constrained('satkers')->nullOnDelete();
            /* Kunci mesin untuk menyaring, mis. "masuk", "berkas"; kalimatnya di `ringkasan`. */
            $t->string('aksi', 60)->index();
            $t->string('kelompok', 20)->index();
            $t->string('ringkasan', 500);
            $t->string('subjek_tipe', 40)->nullable();
            $t->unsignedBigInteger('subjek_id')->nullable();
            /* Sebelum → sesudah; tidak pernah berisi password atau token. */
            $t->json('rincian')->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('agen', 255)->nullable();

            $t->index(['subjek_tipe', 'subjek_id']);
            $t->index(['user_id', 'waktu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_aktivitas');
    }
};
