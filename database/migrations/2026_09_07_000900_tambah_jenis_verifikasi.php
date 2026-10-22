<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Membedakan LHV (UKI) dan CHV (Inspektorat) di tabel yang sama. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifikasis', function (Blueprint $table) {
            $table->string('jenis', 3)->default('CHV')->after('id');
            $table->index('jenis');
        });

        // Yang sudah ada semuanya surat Inspektorat.
        DB::table('verifikasis')->update(['jenis' => 'CHV']);
    }

    public function down(): void
    {
        Schema::table('verifikasis', function (Blueprint $table) {
            $table->dropIndex(['jenis']);
            $table->dropColumn('jenis');
        });
    }
};
