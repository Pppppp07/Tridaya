<?php

use App\Enums\JenisReferensi;
use App\Models\Referensi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Warna kategori internal. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referensis', function (Blueprint $t) {
            $t->string('warna', 16)->nullable()->after('keterangan');
        });

        $awal = [
            'Perjalanan dinas dan penginapan' => 'hijau',
            'Barang milik negara'             => 'merah',
            'Pengadaan barang dan jasa'       => 'jingga',
            'Honorarium dan standar biaya'    => 'kuning',
            'Penerimaan negara bukan pajak'   => 'biru',
            'Pertanggungjawaban belanja'      => 'ungu',
            'Ketidaksesuaian pedoman'         => 'tosca',
            'Lainnya'                         => 'abu',
        ];

        foreach ($awal as $nama => $warna) {
            Referensi::where('jenis', JenisReferensi::KATEGORI_INTERN->value)
                ->where('nama', $nama)->update(['warna' => $warna]);
        }
    }

    public function down(): void
    {
        Schema::table('referensis', function (Blueprint $t) {
            $t->dropColumn('warna');
        });
    }
};
