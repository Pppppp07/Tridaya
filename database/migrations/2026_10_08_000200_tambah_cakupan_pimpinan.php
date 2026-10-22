<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Cakupan Pimpinan terpisah dari unit kerja penanggung jawab (peran Satker).
            $table->foreignId('pimpinan_satker_id')->nullable()->constrained('satkers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('pimpinan_satker_id'));
    }
};
