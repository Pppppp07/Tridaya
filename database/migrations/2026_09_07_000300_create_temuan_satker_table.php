<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Satu temuan bisa mengenai beberapa satuan kerja. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temuan_satker', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temuan_id')->constrained('temuans')->cascadeOnDelete();
            $table->foreignId('satker_id')->constrained('satkers')->cascadeOnDelete();
            $table->unique(['temuan_id', 'satker_id'], 'temuan_satker_unik');
        });

        // Isi lama dipindahkan dulu, baru kolomnya dibuang.
        if (Schema::hasColumn('temuans', 'satker_id')) {
            DB::table('temuans')->whereNotNull('satker_id')->orderBy('id')
                ->chunk(200, function ($baris) {
                    DB::table('temuan_satker')->insertOrIgnore(
                        $baris->map(fn ($t) => [
                            'temuan_id' => $t->id,
                            'satker_id' => $t->satker_id,
                        ])->all()
                    );
                });

            /* Urutan mengikat: lepas kunci asing, buang indeks, baru buang kolom (MySQL dan
               SQLite berbeda aturan). */
            Schema::table('temuans', function (Blueprint $table) {
                $table->dropForeign(['satker_id']);
            });
            Schema::table('temuans', function (Blueprint $table) {
                $table->dropIndex(['satker_id']);
            });
            Schema::table('temuans', function (Blueprint $table) {
                $table->dropColumn('satker_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('temuans', function (Blueprint $table) {
            $table->foreignId('satker_id')->nullable()->after('laporan_id')
                  ->constrained('satkers')->nullOnDelete();
        });

        // Turun tangga hanya bisa mengembalikan satuan kerja pertama.
        DB::table('temuan_satker')->orderBy('temuan_id')->orderBy('id')
            ->get()->groupBy('temuan_id')->each(function ($baris, $temuanId) {
                DB::table('temuans')->where('id', $temuanId)
                    ->update(['satker_id' => $baris->first()->satker_id]);
            });

        Schema::dropIfExists('temuan_satker');
    }
};
