<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan akun sendiri (28 Sep) — Profil → Pemberitahuan dan Tampilan:
 * sembulan kabar, kabar lewat surel, gerak animasi, halaman pertama sesudah
 * masuk. Satu kolom JSON; yang kosong berarti bawaan perannya
 * (App\Support\Setelan::bawaan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->json('pengaturan')->nullable()->after('terakhir_masuk_pada');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('pengaturan');
        });
    }
};
