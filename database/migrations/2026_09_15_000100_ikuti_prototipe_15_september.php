<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Rekomendasi tidak menempuh proses — yang berjalan tindak lanjut tiap satuan kerja (sasarans). */
return new class extends Migration
{
    public function up(): void
    {
        /* ---- 1. Satuan kerja: nama pendek disimpan ---- */
        Schema::table('satkers', function (Blueprint $t) {
            $t->string('nama_pendek', 80)->nullable()->after('nama');
        });

        /* ---- 2. Baris penugasan: penilaian, SIPTL, pemberkasan ulang ---- */
        Schema::table('sasarans', function (Blueprint $t) {
            /* Validasi UKI terpisah dari `hasil` Inspektorat. */
            $t->string('hasil_uki', 2)->nullable()->after('hasil');

            /* Tanggal unggah SIPTL dikunci; hanya pengiriman ulang atas penolakan BPK yang
               mengosongkannya. */
            $t->string('status_bpk', 2)->nullable()->after('hasil_uki');
            $t->date('siptl_tanggal')->nullable()->after('status_bpk');
            $t->text('catatan_bpk')->nullable()->after('siptl_tanggal');
            $t->date('tgl_pantau')->nullable()->after('catatan_bpk');

            /* Pemberkasan ulang yang berlaku; dikosongkan saat perbaikan dikirim (arsipnya di
               `pengembalians`). */
            $t->string('kembali_dari', 20)->nullable()->after('tgl_pantau');
            $t->text('alasan_perbaikan')->nullable()->after('kembali_dari');
            $t->date('batas_perbaikan')->nullable()->after('alasan_perbaikan');
            $t->text('keterangan_setba')->nullable()->after('batas_perbaikan');
            $t->json('dokumen_diminta')->nullable()->after('keterangan_setba');
        });

        /* Unggahan SIPTL diturunkan dari rekomendasi ke tiap barisnya. */
        foreach (DB::table('rekomendasis')->whereNotNull('siptl_tanggal')->get() as $r) {
            $tindakan = DB::table('tindakans')->where('rekomendasi_id', $r->id)->pluck('id');
            DB::table('sasarans')->whereIn('tindakan_id', $tindakan)->update([
                'siptl_tanggal' => $r->siptl_tanggal,
                'status_bpk'    => $r->status,
                'tgl_pantau'    => $r->siptl_dicatat_pada,
            ]);
        }

        Schema::create('riwayat_statuses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sasaran_id')->constrained('sasarans')->cascadeOnDelete();
            $t->string('sumber', 8);                 // UKI, Itjen, SIPTL
            $t->string('dari', 4)->default('');      // kosong: belum pernah berstatus
            $t->string('ke', 4);
            $t->string('oleh', 80);
            $t->date('tanggal');
            $t->text('catatan')->nullable();
            /* Kunci yang menyatukan butir satu putusan jadi satu baris riwayat. */
            $t->string('aksi_id', 40)->nullable()->index();
            $t->string('jenis_aksi', 20)->nullable();   // kembaliBpk
            $t->timestamps();
            $t->index(['sasaran_id', 'tanggal']);
        });

        /* ---- 3. Rekomendasi tidak lagi punya posisi ---- */
        Schema::table('rekomendasis', function (Blueprint $t) {
            $t->dropIndex(['status', 'posisi']);
        });
        Schema::table('rekomendasis', function (Blueprint $t) {
            $t->dropColumn(['posisi', 'siptl_tanda_terima']);
        });

        /* Dokumen yang diminta, per bentuk tindak lanjut. */
        Schema::table('tindakans', function (Blueprint $t) {
            $t->json('dokumen')->nullable()->after('catatan');
        });

        /* ---- 4. Arsip putusan dan pengembalian ---- */
        Schema::table('telaahs', function (Blueprint $t) {
            /* Yang memutus dan yang mengetik dicatat terpisah. */
            $t->string('label_pencatat', 40)->nullable()->after('label_oleh');
            $t->date('batas_waktu')->nullable()->after('perihal');
            $t->json('dokumen')->nullable()->after('batas_waktu');
            $t->string('aksi_id', 40)->nullable()->index()->after('dokumen');
        });

        Schema::table('pengembalians', function (Blueprint $t) {
            $t->string('dari', 20)->nullable()->after('label_oleh');   // UKI, Inspektorat, BPK
            $t->date('batas_waktu')->nullable()->after('alasan');
            $t->text('keterangan_setba')->nullable()->after('batas_waktu');
            $t->json('dokumen')->nullable()->after('keterangan_setba');
            $t->string('aksi_id', 40)->nullable()->index()->after('dokumen');
        });

        Schema::table('permintaan_dokumens', function (Blueprint $t) {
            $t->string('dari', 20)->nullable()->after('peran_peminta');
            $t->text('alasan')->nullable()->after('catatan');
        });

        /* Surat hasil LHV/CHV: unik pada jenis + nomor; satu nomor boleh memutus beberapa baris. */
        Schema::table('verifikasis', function (Blueprint $t) {
            $t->string('periode', 40)->nullable()->change();
            $t->string('perihal', 500)->nullable()->after('tgl_surat');
            $t->string('nomor_lhv')->nullable()->after('pejabat');
            $t->date('tgl_lhv')->nullable()->after('nomor_lhv');
        });
        Schema::table('verifikasis', function (Blueprint $t) {
            $t->dropUnique(['nomor_surat']);
        });
        Schema::table('verifikasis', function (Blueprint $t) {
            $t->unique(['jenis', 'nomor_surat'], 'verifikasi_jenis_nomor_unik');
        });

        Schema::table('keputusan_verifikasis', function (Blueprint $t) {
            /* Indeks sendiri lebih dulu: di MySQL indeks unik lama menopang kunci asing
               verifikasi_id. */
            $t->index('verifikasi_id', 'keputusan_verifikasi_idx');
        });
        Schema::table('keputusan_verifikasis', function (Blueprint $t) {
            $t->dropUnique('verifikasi_rekomendasi_unik');
        });
        Schema::table('keputusan_verifikasis', function (Blueprint $t) {
            $t->foreignId('sasaran_id')->nullable()->after('rekomendasi_id')
                ->constrained('sasarans')->nullOnDelete();
            $t->text('catatan_umum')->nullable()->after('catatan');
            $t->json('catatan_satker')->nullable()->after('catatan_umum');
            $t->string('aksi_id', 40)->nullable()->index()->after('catatan_satker');
        });

        Schema::table('surats', function (Blueprint $t) {
            $t->string('tautan', 500)->nullable()->after('catatan');
        });

        /* ---- 5. Berkas: tidak bisa ditarik, membawa sebutannya ---- */
        Schema::table('lampirans', function (Blueprint $t) {
            $t->dropForeign(['ditarik_oleh']);
        });
        Schema::table('lampirans', function (Blueprint $t) {
            $t->dropColumn(['ditarik_pada', 'ditarik_oleh', 'sebab_tarik', 'catatan_tarik']);
        });
        Schema::table('lampirans', function (Blueprint $t) {
            $t->foreignId('tindakan_id')->nullable()->after('sasaran_id')
                ->constrained('tindakans')->nullOnDelete();
            /* Nama jenis dokumen sebagaimana diminta. */
            $t->string('label_jenis')->nullable()->after('jenis_dokumen_id');
            $t->string('label_oleh', 80)->nullable()->after('diunggah_oleh');
            /* Surat pemeriksaan asli: tidak pernah diberikan ke satuan kerja. */
            $t->boolean('surat_asli')->default(false)->after('label_oleh');
        });

        Schema::dropIfExists('permintaan_ubahs');

        /* ---- 6. Draf tanggapan dan tolakan BPK ---- */
        Schema::create('draf_tanggapans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sasaran_id')->unique()->constrained('sasarans')->cascadeOnDelete();
            $t->text('uraian')->nullable();
            $t->date('tanggal')->nullable();
            $t->json('bukti')->nullable();
            $t->json('setoran')->nullable();
            $t->json('penuhi')->nullable();
            /* Jumlah dan waktu simpan terakhir. */
            $t->unsignedInteger('kali')->default(1);
            $t->date('terakhir');
            $t->timestamps();
        });

        Schema::create('tolakan_bpks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rekomendasi_id')->constrained('rekomendasis')->cascadeOnDelete();
            $t->foreignId('sasaran_id')->nullable()->constrained('sasarans')->nullOnDelete();
            $t->unsignedBigInteger('nilai')->default(0);
            $t->date('tanggal');
            $t->text('catatan')->nullable();
            $t->timestamps();
        });

        /* Draf Catat laporan baru, satu per pengguna. */
        Schema::create('draf_laporans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->json('isian');
            $t->timestamps();
        });

        /* ---- 7. Pemberitahuan: beberapa satuan kerja dan bentuk tindak lanjutnya ---- */
        Schema::create('notifikasi_satker', function (Blueprint $t) {
            $t->id();
            $t->foreignId('notifikasi_id')->constrained('notifikasis')->cascadeOnDelete();
            $t->foreignId('satker_id')->constrained('satkers')->cascadeOnDelete();
            $t->unique(['notifikasi_id', 'satker_id'], 'notifikasi_satker_unik');
        });

        foreach (DB::table('notifikasis')->whereNotNull('satker_id')->get(['id', 'satker_id']) as $n) {
            DB::table('notifikasi_satker')->insert(['notifikasi_id' => $n->id, 'satker_id' => $n->satker_id]);
        }

        Schema::table('notifikasis', function (Blueprint $t) {
            $t->dropForeign(['satker_id']);
        });
        Schema::table('notifikasis', function (Blueprint $t) {
            $t->dropColumn('satker_id');
        });
        Schema::table('notifikasis', function (Blueprint $t) {
            $t->foreignId('tindakan_id')->nullable()->after('rekomendasi_id')
                ->constrained('tindakans')->nullOnDelete();
        });

        /* Empat bagian riwayat di halaman rincian sudah lebur jadi satu. */
        DB::table('notifikasis')->whereIn('blok', ['r-kembali', 'r-telaah', 'r-verifikasi'])
            ->update(['blok' => 'r-riwayat']);
    }

    public function down(): void
    {
        Schema::table('notifikasis', function (Blueprint $t) {
            $t->dropForeign(['tindakan_id']);
        });
        Schema::table('notifikasis', function (Blueprint $t) {
            $t->dropColumn('tindakan_id');
            $t->foreignId('satker_id')->nullable()->constrained('satkers')->nullOnDelete();
        });
        Schema::dropIfExists('notifikasi_satker');

        Schema::dropIfExists('draf_laporans');
        Schema::dropIfExists('tolakan_bpks');
        Schema::dropIfExists('draf_tanggapans');

        Schema::create('permintaan_ubahs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rekomendasi_id')->constrained('rekomendasis')->cascadeOnDelete();
            $t->foreignId('sasaran_id')->nullable()->constrained('sasarans')->nullOnDelete();
            $t->string('jenis', 30);
            $t->text('alasan');
            $t->foreignId('lampiran_sasaran_id')->nullable()->constrained('lampirans')->nullOnDelete();
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->string('label_pengaju')->nullable();
            $t->date('tanggal');
            $t->string('status', 20)->default('menunggu');
            $t->foreignId('diputus_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->string('label_pemutus')->nullable();
            $t->date('tgl_putus')->nullable();
            $t->text('catatan_putus')->nullable();
            $t->timestamps();
            $t->index(['rekomendasi_id', 'status']);
        });

        Schema::table('lampirans', function (Blueprint $t) {
            $t->dropForeign(['tindakan_id']);
        });
        Schema::table('lampirans', function (Blueprint $t) {
            $t->dropColumn(['tindakan_id', 'label_jenis', 'label_oleh', 'surat_asli']);
            $t->timestamp('ditarik_pada')->nullable();
            $t->foreignId('ditarik_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->string('sebab_tarik', 30)->nullable();
            $t->text('catatan_tarik')->nullable();
        });

        Schema::table('surats', fn (Blueprint $t) => $t->dropColumn('tautan'));

        Schema::table('keputusan_verifikasis', function (Blueprint $t) {
            $t->dropForeign(['sasaran_id']);
        });
        Schema::table('keputusan_verifikasis', function (Blueprint $t) {
            $t->dropIndex(['aksi_id']);
            $t->dropColumn(['sasaran_id', 'catatan_umum', 'catatan_satker', 'aksi_id']);
            $t->unique(['verifikasi_id', 'rekomendasi_id'], 'verifikasi_rekomendasi_unik');
        });
        Schema::table('keputusan_verifikasis', function (Blueprint $t) {
            $t->dropIndex('keputusan_verifikasi_idx');
        });

        Schema::table('verifikasis', function (Blueprint $t) {
            $t->dropUnique('verifikasi_jenis_nomor_unik');
        });
        Schema::table('verifikasis', function (Blueprint $t) {
            $t->dropColumn(['perihal', 'nomor_lhv', 'tgl_lhv']);
            $t->unique('nomor_surat');
        });

        Schema::table('permintaan_dokumens', fn (Blueprint $t) => $t->dropColumn(['dari', 'alasan']));

        Schema::table('pengembalians', function (Blueprint $t) {
            $t->dropIndex(['aksi_id']);
            $t->dropColumn(['dari', 'batas_waktu', 'keterangan_setba', 'dokumen', 'aksi_id']);
        });

        Schema::table('telaahs', function (Blueprint $t) {
            $t->dropIndex(['aksi_id']);
            $t->dropColumn(['label_pencatat', 'batas_waktu', 'dokumen', 'aksi_id']);
        });

        Schema::table('tindakans', fn (Blueprint $t) => $t->dropColumn('dokumen'));

        Schema::table('rekomendasis', function (Blueprint $t) {
            $t->string('posisi', 20)->nullable();
            $t->string('siptl_tanda_terima')->nullable();
            $t->index(['status', 'posisi']);
        });

        Schema::dropIfExists('riwayat_statuses');

        Schema::table('sasarans', function (Blueprint $t) {
            $t->dropColumn(['hasil_uki', 'status_bpk', 'siptl_tanggal', 'catatan_bpk', 'tgl_pantau',
                'kembali_dari', 'alasan_perbaikan', 'batas_perbaikan', 'keterangan_setba', 'dokumen_diminta']);
        });

        Schema::table('satkers', fn (Blueprint $t) => $t->dropColumn('nama_pendek'));
    }
};
