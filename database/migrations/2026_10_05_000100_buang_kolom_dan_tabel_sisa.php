<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Membuang tabel dan kolom sisa pengembangan. */
return new class extends Migration
{
    /** Kolom biasa yang dibuang, per tabel (yang berkunci asing diurus terpisah). */
    private const KOLOM = [
        'lampirans'             => ['nama_simpan', 'mime', 'ukuran'],
        'laporans'              => ['jenis_periksa'],
        'riwayat_berkas'        => ['posisi_dari', 'posisi_ke', 'status_dari', 'status_ke'],
        'sasarans'              => ['nilai_ss', 'nilai_memadai'],
        'satkers'               => ['provinsi', 'jenis'],
        'users'                 => ['email_verified_at'],
        'keputusan_verifikasis' => ['diakui_masih_terbuka'],
        'referensis'            => ['keterangan', 'warna'],
        'rekomendasis'          => ['siptl_tanggal', 'siptl_status', 'siptl_catatan', 'siptl_dicatat_pada'],
    ];

    public function up(): void
    {
        if (Schema::hasColumn('lampirans', 'jenis_dokumen_id')) {
            Schema::table('lampirans', function (Blueprint $t) {
                $t->dropConstrainedForeignId('jenis_dokumen_id');
            });
        }
        DB::table('referensis')->where('jenis', 'jenis_dokumen')->delete();

        foreach (self::KOLOM as $tabel => $kolom) {
            $ada = array_values(array_filter($kolom, fn ($k) => Schema::hasColumn($tabel, $k)));
            if ($ada) {
                Schema::table($tabel, fn (Blueprint $t) => $t->dropColumn($ada));
            }
        }

        Schema::dropIfExists('password_reset_tokens');
    }

    /** Bentuk tabel kembali, isinya tidak (isi lama ada di cadangan basis data). */
    public function down(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });

        Schema::table('lampirans', function (Blueprint $t) {
            $t->foreignId('jenis_dokumen_id')->nullable()->after('tindakan_id')->constrained('referensis')->nullOnDelete();
            $t->string('nama_simpan')->nullable()->after('nama_asli');
            $t->string('mime', 100)->nullable()->after('nama_simpan');
            $t->unsignedBigInteger('ukuran')->nullable()->after('mime');
        });
        Schema::table('laporans', fn (Blueprint $t) => $t->string('jenis_periksa', 16)->nullable()->after('sumber'));
        Schema::table('riwayat_berkas', function (Blueprint $t) {
            $t->string('posisi_dari', 20)->nullable()->after('aksi');
            $t->string('posisi_ke', 20)->nullable()->after('posisi_dari');
            $t->string('status_dari', 2)->nullable()->after('posisi_ke');
            $t->string('status_ke', 2)->nullable()->after('status_dari');
        });
        Schema::table('sasarans', function (Blueprint $t) {
            $t->unsignedBigInteger('nilai_ss')->nullable()->after('nilai');
            $t->unsignedBigInteger('nilai_memadai')->nullable()->after('nilai_ss');
        });
        Schema::table('satkers', function (Blueprint $t) {
            $t->string('provinsi', 64)->nullable()->after('nama_pendek');
            $t->string('jenis', 20)->default('balai')->after('provinsi');
        });
        Schema::table('users', fn (Blueprint $t) => $t->timestamp('email_verified_at')->nullable()->after('email'));
        Schema::table('keputusan_verifikasis', fn (Blueprint $t) => $t->boolean('diakui_masih_terbuka')->default(false)->after('catatan_satker'));
        Schema::table('referensis', function (Blueprint $t) {
            $t->string('keterangan')->nullable()->after('nama');
            $t->string('warna', 16)->nullable()->after('keterangan');
        });
        Schema::table('rekomendasis', function (Blueprint $t) {
            $t->date('siptl_tanggal')->nullable();
            $t->string('siptl_status', 2)->nullable();
            $t->text('siptl_catatan')->nullable();
            $t->date('siptl_dicatat_pada')->nullable();
        });
    }
};
