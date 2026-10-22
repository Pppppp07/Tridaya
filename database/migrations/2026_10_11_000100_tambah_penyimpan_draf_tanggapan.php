<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Draf tindak lanjut terpantau Setba: nama penyimpannya ikut tampil. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draf_tanggapans', function (Blueprint $t) {
            $t->foreignId('disimpan_oleh')->nullable()->after('kali')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('draf_tanggapans', function (Blueprint $t) {
            $t->dropConstrainedForeignId('disimpan_oleh');
        });
    }
};
