<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Hasil surat CHV pindah dari sumbu BPK ke sumbu Itjen. */
return new class extends Migration
{
    private const KE_ITJEN = ['SS' => 'M', 'BS' => 'BM', 'TD' => 'BM'];

    public function up(): void
    {
        foreach (self::KE_ITJEN as $lama => $baru) {
            DB::table('keputusan_verifikasis')->where('hasil', $lama)->update(['hasil' => $baru]);
        }
    }

    public function down(): void
    {
        // Turun tangga tidak bisa utuh: yang dikembalikan BS.
        DB::table('keputusan_verifikasis')->where('hasil', 'M')->update(['hasil' => 'SS']);
        DB::table('keputusan_verifikasis')->where('hasil', 'BM')->update(['hasil' => 'BS']);
    }
};
