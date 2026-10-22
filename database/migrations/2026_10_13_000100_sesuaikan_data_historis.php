<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tempat untuk kolom lembar pemantauan Setba yang belum punya padanan: bentuk tindak lanjut yang
 * tidak ada di daftar, keterangan paket, klasifikasi rencana aksi, dan uraian verifikasi. Judul
 * temuan diperlebar karena ada judul 270 karakter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temuans', function (Blueprint $table) {
            $table->string('judul', 500)->change();
        });
        Schema::table('tindakans', function (Blueprint $table) {
            $table->string('bentuk_teks', 500)->nullable()->after('bentuk_id');
            $table->string('keterangan_paket', 500)->nullable()->after('bentuk_teks');
            $table->string('klasifikasi_renaksi', 20)->nullable()->after('tgl_renaksi');
        });
        Schema::table('sasarans', function (Blueprint $table) {
            $table->text('uraian_verifikasi')->nullable()->after('catatan_bpk');
        });
    }

    public function down(): void
    {
        Schema::table('sasarans', function (Blueprint $table) {
            $table->dropColumn('uraian_verifikasi');
        });
        Schema::table('tindakans', function (Blueprint $table) {
            $table->dropColumn(['bentuk_teks', 'keterangan_paket', 'klasifikasi_renaksi']);
        });
        Schema::table('temuans', function (Blueprint $table) {
            $table->string('judul', 255)->change();
        });
    }
};
