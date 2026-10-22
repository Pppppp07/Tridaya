<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unit yang dipantau: uraian, nilai keuangan, dan hasil pemantauan melekat di tingkat ini.
        Schema::create('rekomendasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('temuan_id')->constrained('temuans')->cascadeOnDelete();
            $table->string('kode', 40)->unique();
            $table->unsignedSmallInteger('nomor_urut')->default(1);
            $table->text('uraian');
            $table->foreignId('bentuk_id')->nullable()->constrained('referensis')->nullOnDelete();
            $table->unsignedBigInteger('nilai_pulih')->default(0);

            // Penyetoran boleh dicicil (PP 38/2016, sampai 24 bulan); `kunci_angsur` menahan
            // setoran melebihi rencana.
            $table->unsignedSmallInteger('rencana_angsur')->default(0);
            $table->boolean('kunci_angsur')->default(false);

            // penugasan — berbeda-beda antar rekomendasi walau satu temuan
            $table->foreignId('satker_id')->nullable()->constrained('satkers')->nullOnDelete();
            $table->date('tenggat_jawab')->nullable();    // batas menjawab, ada dasar hukumnya
            $table->date('target_selesai')->nullable();   // batas menuntaskan, bisa bertahun
            $table->text('catatan')->nullable();

            // dua sumbu yang wajib dipisah
            $table->string('status', 2)->default('BT');
            $table->string('posisi', 20)->default('satker');

            // hanya terisi bila statusnya TD
            $table->foreignId('alasan_td_id')->nullable()->constrained('referensis')->nullOnDelete();
            $table->text('catatan_td')->nullable();

            // jejak penyampaian ke BPK, hanya untuk jalur LHP
            $table->date('siptl_tanggal')->nullable();
            $table->string('siptl_tanda_terima')->nullable();

            $table->timestamps();
            $table->index(['status', 'posisi']);
            $table->index('satker_id');
            $table->index('tenggat_jawab');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekomendasis');
    }
};
