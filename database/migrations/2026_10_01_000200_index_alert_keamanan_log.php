<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_keamanan_log', function (Blueprint $t) {
            $t->foreignId('alert_keamanan_id')->constrained('alert_keamanan')->restrictOnDelete();
            /* Tanpa FK supaya log tetap bisa dipangkas menurut retensi. */
            $t->unsignedBigInteger('log_aktivitas_id')->index();
            $t->unique(['alert_keamanan_id', 'log_aktivitas_id'], 'alert_log_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_keamanan_log');
    }
};
