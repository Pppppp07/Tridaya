<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Tiga medan dari lembar pemantauan (PTL_BPSDM_Clean.csv) yang belum punya tempat. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sasarans', function (Blueprint $t) {
            /* Sengaja nullable, bukan default 0. Lihat keterangan di atas. */
            $t->unsignedBigInteger('nilai_ss')->nullable()->after('nilai');
            $t->unsignedBigInteger('nilai_memadai')->nullable()->after('nilai_ss');
        });

        Schema::table('laporans', function (Blueprint $t) {
            $t->string('jenis_periksa', 16)->nullable()->after('sumber');
        });

        Schema::table('satkers', function (Blueprint $t) {
            $t->string('provinsi', 64)->nullable()->after('nama');
        });
    }

    public function down(): void
    {
        Schema::table('sasarans', fn (Blueprint $t) => $t->dropColumn(['nilai_ss', 'nilai_memadai']));
        Schema::table('laporans', fn (Blueprint $t) => $t->dropColumn('jenis_periksa'));
        Schema::table('satkers', fn (Blueprint $t) => $t->dropColumn('provinsi'));
    }
};
