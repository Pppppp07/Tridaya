<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Peran lama tetap menjadi bawaan; identitas/NIP tidak digandakan.
            $table->json('peran_tambahan')->nullable();
            $table->unsignedInteger('versi_akses')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['peran_tambahan', 'versi_akses']);
        });
    }
};
