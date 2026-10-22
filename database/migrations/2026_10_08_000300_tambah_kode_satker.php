<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('satkers', function (Blueprint $table) {
            // Kode satker resmi (6 angka) untuk mencocokkan data lama dan unit dari SSO; `kode` tetap kunci IRM.
            $table->string('kode_satker', 6)->nullable()->unique()->after('kode');
        });
    }

    public function down(): void
    {
        Schema::table('satkers', function (Blueprint $table) {
            $table->dropUnique(['kode_satker']);
            $table->dropColumn('kode_satker');
        });
    }
};
