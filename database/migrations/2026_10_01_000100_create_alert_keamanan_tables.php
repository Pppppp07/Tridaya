<?php

use App\Support\Aktivitas;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemantauan_keamanan', function (Blueprint $t) {
            $t->unsignedTinyInteger('id')->primary();
            $t->dateTime('mulai_pada');
        });
        /* Titik mulai eksplisit: log lama tidak diberi tuduhan secara surut. */
        DB::table('pemantauan_keamanan')->insert(['id' => 1, 'mulai_pada' => Aktivitas::waktuKini()]);

        Schema::create('alert_keamanan', function (Blueprint $t) {
            $t->id();
            $t->string('aturan', 60);
            $t->string('tingkat', 20);
            $t->string('judul', 150);
            $t->text('alasan');
            $t->string('status', 30)->default('baru');
            $t->dateTime('pertama_pada');
            $t->dateTime('terakhir_pada');
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('nama', 150)->nullable();
            $t->string('peran', 20)->nullable();
            $t->string('ip', 45)->nullable();
            $t->string('sumber', 30);
            /* ID dan fakta terpilih, bukan salinan request body atau kredensial. */
            $t->json('bukti');
            $t->string('cakupan', 64)->index();
            $t->string('kunci_aktif', 64)->nullable()->unique();
            $t->string('kunci_peristiwa', 64)->nullable()->unique();
            $t->timestamps();
            $t->index(['status', 'terakhir_pada']);
            $t->index(['tingkat', 'terakhir_pada']);
        });
        Schema::create('tinjauan_alert', function (Blueprint $t) {
            $t->id();
            $t->foreignId('alert_keamanan_id')->constrained('alert_keamanan')->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('nama', 150);
            $t->string('peran', 20);
            $t->string('keputusan', 30);
            $t->text('catatan');
            $t->dateTime('waktu');
            $t->index(['alert_keamanan_id', 'waktu']);
        });
        Schema::table('log_aktivitas', function (Blueprint $t) {
            $t->index(['aksi', 'ip', 'waktu'], 'log_aksi_ip_waktu');
            $t->index(['aksi', 'user_id', 'waktu'], 'log_aksi_user_waktu');
        });
    }

    public function down(): void
    {
        Schema::table('log_aktivitas', function (Blueprint $t) {
            $t->dropIndex('log_aksi_ip_waktu');
            $t->dropIndex('log_aksi_user_waktu');
        });
        Schema::dropIfExists('tinjauan_alert');
        Schema::dropIfExists('alert_keamanan');
        Schema::dropIfExists('pemantauan_keamanan');
    }
};
