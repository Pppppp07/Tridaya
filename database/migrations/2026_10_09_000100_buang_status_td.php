<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Status TD tidak dipakai BPSDM: alasan sah dan catatannya ikut dibuang. */
return new class extends Migration
{
    /** Daftar baku alasan sah, untuk `down()`. */
    private const ALASAN = [
        'Keadaan kahar',
        'Sakit, dibuktikan surat keterangan dokter',
        'Menjadi tersangka dan ditahan',
        'Menjadi terpidana',
        'Alasan sah lain berdasarkan peraturan perundang-undangan',
    ];

    public function up(): void
    {
        if (Schema::hasColumn('rekomendasis', 'alasan_td_id')) {
            Schema::table('rekomendasis', fn (Blueprint $t) => $t->dropForeign(['alasan_td_id']));
            // Indeks tambahan pada kolom ini ikut terbuang di MySQL, tidak di SQLite.
            if (Schema::hasIndex('rekomendasis', 'rekomendasis_alasan_td_id_index')) {
                Schema::table('rekomendasis', fn (Blueprint $t) => $t->dropIndex('rekomendasis_alasan_td_id_index'));
            }
            Schema::table('rekomendasis', fn (Blueprint $t) => $t->dropColumn('alasan_td_id'));
        }
        if (Schema::hasColumn('rekomendasis', 'catatan_td')) {
            Schema::table('rekomendasis', fn (Blueprint $t) => $t->dropColumn('catatan_td'));
        }
        DB::table('referensis')->where('jenis', 'alasan_td')->delete();
    }

    public function down(): void
    {
        Schema::table('rekomendasis', function (Blueprint $t) {
            $t->foreignId('alasan_td_id')->nullable()->after('status')->constrained('referensis')->nullOnDelete();
            $t->text('catatan_td')->nullable()->after('alasan_td_id');
        });
        foreach (self::ALASAN as $i => $nama) {
            DB::table('referensis')->insert([
                'jenis' => 'alasan_td', 'nama' => $nama, 'urutan' => $i + 1, 'aktif' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
};
