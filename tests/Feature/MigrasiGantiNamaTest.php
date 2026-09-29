<?php

namespace Tests\Feature;

use App\Models\DrafTanggapan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Migrasi ganti nama 29 Sep (kolom tautan → link, kabar_dilihat_pada →
 * notifikasi_dilihat_pada, kunci JSON surel/sembul/gerak/tautan). Kata Hizkia:
 * "untuk penamaan kolom di database atau lainnya juga coba sesuaikan ulang
 * penamaannya". Diuji bolak-balik: data berbentuk baru dikembalikan ke bentuk
 * lama lewat down(), lalu dibawa lagi lewat up() — hasilnya harus persis sama.
 */
class MigrasiGantiNamaTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    public function test_nama_lama_di_basis_data_dibawa_bolak_balik(): void
    {
        $this->siapkanDataContoh();
        $m = require database_path('migrations/2026_09_29_000100_ganti_nama_istilah.php');

        $u = $this->akun('setba@contoh.test');
        $pengaturan = ['popup' => false, 'email' => true, 'animasi' => 'kurang', 'beranda' => 'pemberitahuan'];
        DB::table('users')->where('id', $u->id)->update(['pengaturan' => json_encode($pengaturan)]);
        $log = DB::table('log_aktivitas')->insertGetId([
            'waktu' => now(), 'aksi' => 'akun.password', 'kelompok' => 'akun', 'ringkasan' => 'Mengganti password',
            'rincian' => json_encode(['cara' => 'password', 'email' => 'a@contoh.test', 'sebelum' => ['popup' => true, 'animasi' => 'ikut']]),
        ]);
        $draf = DrafTanggapan::all()->first(fn ($d) => collect($d->bukti)->contains(fn ($b) => isset($b['link'])));
        $this->assertNotNull($draf, 'Data contoh punya draf dengan bukti berupa link.');
        $bukti = DB::table('draf_tanggapans')->where('id', $draf->id)->value('bukti');
        $lampiran = DB::table('lampirans')->whereNotNull('link')->orderBy('id')->first(['id', 'link']);

        /* Mundur: bentuk lama. */
        $m->down();
        $this->assertTrue(Schema::hasColumn('lampirans', 'tautan') && ! Schema::hasColumn('lampirans', 'link'));
        $this->assertTrue(Schema::hasColumn('surats', 'tautan'));
        $this->assertTrue(Schema::hasColumn('users', 'kabar_dilihat_pada') && ! Schema::hasColumn('users', 'notifikasi_dilihat_pada'));
        $this->assertSame(['sembul' => false, 'surel' => true, 'gerak' => 'kurang', 'beranda' => 'kabar'],
            json_decode(DB::table('users')->where('id', $u->id)->value('pengaturan'), true));
        $x = DB::table('log_aktivitas')->find($log);
        $this->assertSame('akun.sandi', $x->aksi);
        $this->assertSame(['cara' => 'sandi', 'surel' => 'a@contoh.test', 'sebelum' => ['sembul' => true, 'gerak' => 'ikut']],
            json_decode($x->rincian, true));
        $this->assertStringContainsString('"tautan"', DB::table('draf_tanggapans')->where('id', $draf->id)->value('bukti'));
        $this->assertSame($lampiran->link, DB::table('lampirans')->where('id', $lampiran->id)->value('tautan'));

        /* Maju lagi: bentuk baru, isinya sama persis. */
        $m->up();
        $this->assertTrue(Schema::hasColumn('lampirans', 'link') && Schema::hasColumn('surats', 'link'));
        $this->assertTrue(Schema::hasColumn('users', 'notifikasi_dilihat_pada'));
        $this->assertSame($pengaturan, json_decode(DB::table('users')->where('id', $u->id)->value('pengaturan'), true));
        $x = DB::table('log_aktivitas')->find($log);
        $this->assertSame('akun.password', $x->aksi);
        $this->assertSame(['cara' => 'password', 'email' => 'a@contoh.test', 'sebelum' => ['popup' => true, 'animasi' => 'ikut']],
            json_decode($x->rincian, true));
        $this->assertSame(json_decode($bukti, true), json_decode(DB::table('draf_tanggapans')->where('id', $draf->id)->value('bukti'), true));
        $this->assertSame($lampiran->link, DB::table('lampirans')->where('id', $lampiran->id)->value('link'));
        /* Kalimat ringkasan yang sudah tercatat tidak disentuh. */
        $this->assertSame('Mengganti password', $x->ringkasan);
    }
}
