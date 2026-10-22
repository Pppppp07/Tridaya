<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draf_historis', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $t->string('nama_berkas');
            $t->string('berkas');
            $t->char('sha256', 64);
            $t->json('isian');
            $t->unsignedInteger('versi')->default(1);
            $t->timestamp('diimpor_pada')->nullable();
            $t->timestamps();
            $t->index(['user_id', 'created_at']);
        });
        // Serialisasi konfirmasi lintas file/user, tanpa mengunci seluruh tabel laporan.
        Schema::create('kunci_impor_historis', fn (Blueprint $t) => $t->unsignedTinyInteger('id')->primary());
        DB::table('kunci_impor_historis')->insert(['id' => 1]);
        Schema::table('sasarans', function (Blueprint $t) {
            $t->foreignId('draf_historis_id')->nullable()->constrained('draf_historis')->restrictOnDelete();
            $t->date('tanggal_acuan_historis')->nullable();
            $t->boolean('siptl_historis')->default(false);
            $t->unsignedBigInteger('nilai_diakui_itjen')->nullable();
            $t->unsignedBigInteger('nilai_diakui_bpk')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sasarans', function (Blueprint $t) {
            $t->dropConstrainedForeignId('draf_historis_id');
            $t->dropColumn(['tanggal_acuan_historis', 'siptl_historis', 'nilai_diakui_itjen', 'nilai_diakui_bpk']);
        });
        Schema::dropIfExists('kunci_impor_historis');
        Schema::dropIfExists('draf_historis');
    }
};
