<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Butir kelengkapan ↔ berkas yang memenuhinya.
        Schema::create('item_permintaan_lampiran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_permintaan_id')->constrained('item_permintaans')->cascadeOnDelete();
            $table->foreignId('lampiran_id')->constrained('lampirans')->cascadeOnDelete();
            $table->unique(['item_permintaan_id', 'lampiran_id'], 'item_lampiran_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_permintaan_lampiran');
    }
};
