<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log aktivitas (27 Sep) — "setiap aktivitas terekam", kata Bang Kamal.
 *
 * Tabel ini hanya bertambah: aplikasi tidak menyediakan jalur ubah maupun
 * hapus bagi siapa pun. Satu-satunya yang mengurangi isinya pemangkasan
 * terjadwal (`model:prune`) untuk baris yang lebih tua dari masa simpannya
 * (config simtlhp.log.simpan_hari).
 *
 * Nama, peran, dan unit pelakunya disalin saat kejadian, bukan cuma ditunjuk
 * lewat user_id: pelakunya boleh berganti peran atau dinonaktifkan kemudian,
 * dan log harus tetap menyebut siapa dia SAAT itu.
 */
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
            /* Kunci mesin, mis. "masuk", "master.unit.pj", "berkas" — untuk
               menyaring. Kalimat untuk dibaca orang ada di `ringkasan`. */
            $t->string('aksi', 60)->index();
            $t->string('kelompok', 20)->index();
            $t->string('ringkasan', 500);
            $t->string('subjek_tipe', 40)->nullable();
            $t->unsignedBigInteger('subjek_id')->nullable();
            /* Sebelum → sesudah, dan keterangan lain. Tidak pernah berisi kata
               sandi atau token. */
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
