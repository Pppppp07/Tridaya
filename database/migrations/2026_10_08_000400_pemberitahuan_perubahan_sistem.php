<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifikasis', function (Blueprint $table) {
            // Pemberitahuan perubahan sistem untuk Admin DTI tidak terikat rekomendasi; ia menunjuk entri log.
            $table->foreignId('rekomendasi_id')->nullable()->change();
            $table->foreignId('log_aktivitas_id')->nullable()->after('tindakan_id')
                ->constrained('log_aktivitas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('notifikasis')->whereNull('rekomendasi_id')->delete();
        Schema::table('notifikasis', function (Blueprint $table) {
            $table->dropConstrainedForeignId('log_aktivitas_id');
            $table->foreignId('rekomendasi_id')->nullable(false)->change();
        });
    }
};
