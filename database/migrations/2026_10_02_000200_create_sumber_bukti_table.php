<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sumber_bukti', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->string('host', 253)->unique();
            $table->string('pola', 20);
            $table->string('prefix', 200)->default('/');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
        DB::table('sumber_bukti')->insert([
            ['nama' => 'Google Drive', 'host' => 'drive.google.com', 'pola' => 'drive', 'prefix' => '/', 'aktif' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nama' => 'Google Docs / Sheets / Slides', 'host' => 'docs.google.com', 'pola' => 'docs', 'prefix' => '/', 'aktif' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        Schema::table('lampirans', function (Blueprint $table) {
            $table->timestamp('ditolak_pada')->nullable();
            $table->foreignId('ditolak_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('alasan_penolakan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lampirans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ditolak_oleh');
            $table->dropColumn(['ditolak_pada', 'alasan_penolakan']);
        });
        Schema::dropIfExists('sumber_bukti');
    }
};
