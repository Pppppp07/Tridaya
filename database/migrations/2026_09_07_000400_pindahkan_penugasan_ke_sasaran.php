<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Memindahkan penugasan dari rekomendasi ke sasaran (satu tindakans + satu sasarans per rekomendasi
 * lama).
 */
return new class extends Migration
{
    /** Enam posisi yang ditempuh berkas tiap satuan kerja. */
    private const TINGKAT_1 = [
        'satker', 'setba_tinjau', 'uki', 'setba_teruskan', 'inspektorat', 'tuntas',
    ];

    /** Posisi lama yang namanya berubah. */
    private const GANTI_NAMA = ['menunggu_surat' => 'tuntas'];

    public function up(): void
    {
        if (Schema::hasColumn('rekomendasis', 'satker_id')) {
            $waktu = now();

            DB::table('rekomendasis')->orderBy('id')->chunk(200, function ($baris) use ($waktu) {
                foreach ($baris as $r) {
                    $tindakanId = DB::table('tindakans')->insertGetId([
                        'rekomendasi_id' => $r->id,
                        'bentuk_id'      => $r->bentuk_id ?? null,
                        'urutan'         => 1,
                        'created_at'     => $waktu,
                        'updated_at'     => $waktu,
                    ]);

                    // Rekomendasi tanpa satuan kerja: tindakan tetap dibuat supaya bentuknya tidak
                    // hilang.
                    if (! $r->satker_id) {
                        continue;
                    }

                    $posisi = self::GANTI_NAMA[$r->posisi] ?? $r->posisi;
                    $tingkat1 = in_array($posisi, self::TINGKAT_1, true);

                    DB::table('sasarans')->insert([
                        'tindakan_id' => $tindakanId,
                        'satker_id'   => $r->satker_id,
                        'nilai'       => $r->nilai_pulih ?? 0,
                        // Berkas di tingkat 2 berarti bagian satuan kerjanya sudah tuntas.
                        'posisi'      => $tingkat1 ? $posisi : 'tuntas',
                        'hasil'       => null,
                        'catatan'     => null,
                        'created_at'  => $waktu,
                        'updated_at'  => $waktu,
                    ]);
                }
            });

            // Sama seperti temuans: kunci dulu, indeks, baru kolomnya.
            Schema::table('rekomendasis', function (Blueprint $table) {
                $table->dropForeign(['satker_id']);
                $table->dropForeign(['bentuk_id']);
            });
            Schema::table('rekomendasis', function (Blueprint $table) {
                $table->dropIndex(['satker_id']);
            });
            Schema::table('rekomendasis', function (Blueprint $table) {
                $table->dropColumn(['satker_id', 'bentuk_id']);
            });
        }

        Schema::table('rekomendasis', function (Blueprint $table) {
            $table->string('posisi', 20)->nullable()->default(null)->change();
        });

        // Posisi tingkat 1 kini milik sasaran; rekomendasinya dikosongkan.
        DB::table('rekomendasis')
            ->whereIn('posisi', array_merge(self::TINGKAT_1, array_keys(self::GANTI_NAMA)))
            ->update(['posisi' => null]);
    }

    public function down(): void
    {
        Schema::table('rekomendasis', function (Blueprint $table) {
            $table->foreignId('satker_id')->nullable()->after('kunci_angsur')
                  ->constrained('satkers')->nullOnDelete();
            $table->foreignId('bentuk_id')->nullable()->after('uraian')
                  ->constrained('referensis')->nullOnDelete();
        });

        // Turun tangga hanya mengembalikan sasaran pertama tiap rekomendasi.
        DB::table('sasarans')
            ->join('tindakans', 'sasarans.tindakan_id', '=', 'tindakans.id')
            ->select('tindakans.rekomendasi_id', 'tindakans.bentuk_id',
                     'sasarans.satker_id', 'sasarans.posisi', 'sasarans.id')
            ->orderBy('sasarans.id')
            ->get()
            ->groupBy('rekomendasi_id')
            ->each(function ($baris, $rekId) {
                $x = $baris->first();
                DB::table('rekomendasis')->where('id', $rekId)->update([
                    'satker_id' => $x->satker_id,
                    'bentuk_id' => $x->bentuk_id,
                    'posisi'    => DB::raw("COALESCE(posisi, ".DB::getPdo()->quote($x->posisi).")"),
                ]);
            });

        DB::table('rekomendasis')->whereNull('posisi')->update(['posisi' => 'satker']);

        Schema::table('rekomendasis', function (Blueprint $table) {
            $table->string('posisi', 20)->default('satker')->nullable(false)->change();
        });

        DB::table('sasarans')->delete();
        DB::table('tindakans')->delete();
    }
};
