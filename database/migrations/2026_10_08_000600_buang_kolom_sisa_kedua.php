<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kolom sisa pengembangan yang tidak pernah diisi aplikasi. */
return new class extends Migration
{
    /** Berkunci asing: [tabel, kolom]. */
    private const ASING = [
        ['keputusan_verifikasis', 'alasan_td_id'],
        ['riwayat_berkas', 'sasaran_id'],
        ['surats', 'lampiran_id'],
    ];

    public function up(): void
    {
        foreach (self::ASING as [$tabel, $kolom]) {
            if (! Schema::hasColumn($tabel, $kolom)) {
                continue;
            }
            Schema::table($tabel, fn (Blueprint $t) => $t->dropForeign([$kolom]));
            // Indeks tambahan pada kolom ini ikut terbuang di MySQL, tidak di SQLite.
            if (Schema::hasIndex($tabel, $tabel.'_'.$kolom.'_index')) {
                Schema::table($tabel, fn (Blueprint $t) => $t->dropIndex($tabel.'_'.$kolom.'_index'));
            }
            Schema::table($tabel, fn (Blueprint $t) => $t->dropColumn($kolom));
        }
        if (Schema::hasColumn('verifikasis', 'periode')) {
            Schema::table('verifikasis', fn (Blueprint $t) => $t->dropColumn('periode'));
        }
    }

    public function down(): void
    {
        Schema::table('keputusan_verifikasis', fn (Blueprint $t) => $t->foreignId('alasan_td_id')->nullable()->after('tenggat_baru')->constrained('referensis')->nullOnDelete());
        Schema::table('riwayat_berkas', fn (Blueprint $t) => $t->foreignId('sasaran_id')->nullable()->after('rekomendasi_id')->constrained('sasarans')->nullOnDelete());
        Schema::table('surats', fn (Blueprint $t) => $t->foreignId('lampiran_id')->nullable()->after('link')->constrained('lampirans')->nullOnDelete());
        Schema::table('verifikasis', fn (Blueprint $t) => $t->string('periode', 40)->nullable()->after('jenis'));
    }
};
