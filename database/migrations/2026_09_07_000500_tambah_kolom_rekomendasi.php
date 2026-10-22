<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kolom yang ada di prototipe tapi belum dibuat, termasuk tiga soal SIPTL. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rekomendasis', function (Blueprint $table) {
            // Rujukan penomoran surat LHP, mis. "II.1.1.a" — diketik ikut surat.
            $table->string('ref_lhp', 40)->nullable()->after('kode');

            // Sifat temuan menurut LHP, dari daftar baku.
            $table->foreignId('sifat_id')->nullable()->after('uraian')
                  ->constrained('referensis')->nullOnDelete();

            // Hasil SIPTL disalin Setba apa adanya.
            $table->string('siptl_status', 2)->nullable()->after('siptl_tanda_terima');
            $table->text('siptl_catatan')->nullable()->after('siptl_status');
            // Kapan Setba mencatatnya, beda dari kapan statusnya keluar.
            $table->date('siptl_dicatat_pada')->nullable()->after('siptl_catatan');
        });
    }

    public function down(): void
    {
        Schema::table('rekomendasis', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sifat_id');
            $table->dropColumn(['ref_lhp', 'siptl_status', 'siptl_catatan', 'siptl_dicatat_pada']);
        });
    }
};
