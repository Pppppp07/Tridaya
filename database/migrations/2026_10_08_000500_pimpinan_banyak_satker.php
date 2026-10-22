<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Pimpinan bisa memimpin beberapa Satker, atau semuanya bila Admin DTI memilihnya.
            $table->json('pimpinan_satker_ids')->nullable()->after('pimpinan_satker_id');
            $table->boolean('pimpinan_semua')->default(false)->after('pimpinan_satker_ids');
        });

        foreach (DB::table('users')->whereNotNull('pimpinan_satker_id')->get(['id', 'pimpinan_satker_id']) as $u) {
            DB::table('users')->where('id', $u->id)->update(['pimpinan_satker_ids' => json_encode([(int) $u->pimpinan_satker_id])]);
        }

        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('pimpinan_satker_id'));
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('pimpinan_satker_id')->nullable()->constrained('satkers')->nullOnDelete();
        });
        foreach (DB::table('users')->whereNotNull('pimpinan_satker_ids')->get(['id', 'pimpinan_satker_ids']) as $u) {
            $ids = json_decode((string) $u->pimpinan_satker_ids, true) ?: [];
            DB::table('users')->where('id', $u->id)->update(['pimpinan_satker_id' => $ids[0] ?? null]);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['pimpinan_satker_ids', 'pimpinan_semua']));
    }
};
