<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Akun siap disambungkan ke SSO/eHRM (27 Sep).
 *
 * Masuk lewat SSO berarti orangnya dikenali dari NIP-nya, jadi satu NIP harus
 * menunjuk tepat satu akun — dijaga basis data, bukan cuma kode. NIP lama yang
 * ditulis berspasi (seperti di kartu pegawai) dirapikan dulu jadi 18 angka
 * polos, baru indeks uniknya dipasang. Kalau ternyata ada NIP kembar, migrasi
 * berhenti dengan pesan yang menyebut NIP-nya — lebih baik berhenti daripada
 * diam-diam memilih salah satu akun.
 *
 * `terakhir_masuk_pada` untuk daftar Pengguna dan Keaktifan akun: DTI bisa
 * melihat akun mana yang lama tidak dipakai ("Log lu mati, ada apa?").
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('users')->whereNotNull('nip')->get(['id', 'nip']) as $u) {
            $rapi = preg_replace('/\D/', '', (string) $u->nip);
            if ($rapi !== $u->nip) {
                DB::table('users')->where('id', $u->id)->update(['nip' => $rapi !== '' ? $rapi : null]);
            }
        }
        $kembar = DB::table('users')->whereNotNull('nip')
            ->select('nip')->groupBy('nip')->havingRaw('count(*) > 1')->pluck('nip');
        if ($kembar->isNotEmpty()) {
            throw new RuntimeException('NIP berikut dipakai lebih dari satu akun: '.$kembar->join(', ')
                .'. Rapikan dulu (nonaktifkan dan kosongkan NIP akun yang keliru), lalu jalankan migrasi lagi.');
        }

        Schema::table('users', function (Blueprint $t) {
            $t->unique('nip');
            $t->dateTime('terakhir_masuk_pada')->nullable()->after('aktif');
            $t->index('peran');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropUnique(['nip']);
            $t->dropIndex(['peran']);
            $t->dropColumn('terakhir_masuk_pada');
        });
    }
};
