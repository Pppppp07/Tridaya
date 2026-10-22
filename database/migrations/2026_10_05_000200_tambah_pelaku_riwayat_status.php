<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Akun yang mencatat tiap perubahan status. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riwayat_statuses', function (Blueprint $t) {
            $t->foreignId('oleh_id')->nullable()->after('oleh')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('riwayat_statuses', function (Blueprint $t) {
            $t->dropConstrainedForeignId('oleh_id');
        });
    }
};
