<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pemberitahuan dirombak (26 Sep) — padanan `dilihat` di App prototipe.
 *
 * Tiga keadaan satu kabar: BARU (belum dibaca dan datang sesudah terakhir kali
 * penggunanya membuka daftar pemberitahuan — dihitung di lonceng), BELUM
 * DIBACA (titik biru), SUDAH DIBACA (notifikasi_bacas). "Terakhir kali membuka
 * daftarnya" cukup satu cap waktu per akun: kabar yang lebih baru darinya
 * itulah yang belum pernah tampil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('kabar_dilihat_pada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('kabar_dilihat_pada');
        });
    }
};
