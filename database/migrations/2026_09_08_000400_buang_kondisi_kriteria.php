<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kondisi dan kriteria berhenti disimpan. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('temuans', function (Blueprint $t) {
            $t->dropColumn(['kondisi', 'kriteria']);
        });
    }

    /** Kolomnya kembali, isinya tidak. */
    public function down(): void
    {
        Schema::table('temuans', function (Blueprint $t) {
            $t->text('kondisi')->nullable()->after('judul');
            $t->text('kriteria')->nullable()->after('kondisi');
        });
    }
};
