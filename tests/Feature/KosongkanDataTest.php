<?php

namespace Tests\Feature;

use App\Models\Laporan;
use App\Models\Notifikasi;
use App\Models\Referensi;
use App\Models\Satker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/** `tlhp:kosongkan`: seluruh data berkas dikosongkan, tanpa sisa. */
class KosongkanDataTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    public function test_data_berkas_habis_tapi_data_master_dan_akun_tetap(): void
    {
        $this->assertGreaterThan(0, Laporan::count());

        Artisan::call('tlhp:kosongkan', ['--force' => true]);

        $this->assertSame(0, Laporan::count());
        $this->assertSame(0, Notifikasi::count());
        $this->assertGreaterThan(0, Satker::count());
        $this->assertGreaterThan(0, User::count());
        $this->assertGreaterThan(0, Referensi::count());
    }

    public function test_seluruh_tabel_berkas_ikut_terkosongkan(): void
    {
        /* Draf impor historis menyimpan isi file mentah; ikut dikosongkan bersama laporannya. */
        DB::table('draf_historis')->insert(['user_id' => User::first()->id, 'nama_berkas' => 'lama.xlsx', 'berkas' => 'lama.xlsx',
            'sha256' => str_repeat('b', 64), 'isian' => json_encode(['baris' => []]), 'versi' => 1, 'created_at' => now(), 'updated_at' => now()]);
        Artisan::call('tlhp:kosongkan', ['--force' => true]);

        /* Tabel yang menunjuk laporan — langsung maupun lewat rekomendasi —
           tidak boleh menyisakan satu baris pun. */
        $tabel = ['laporans', 'temuans', 'temuan_satker', 'rekomendasis', 'tindakans', 'sasarans',
            'lampirans', 'riwayat_berkas', 'riwayat_statuses', 'notifikasis', 'notifikasi_satker',
            'notifikasi_bacas', 'permintaan_dokumens', 'item_permintaans', 'pemulihans',
            'tolakan_bpks', 'pengembalians', 'telaahs', 'surats', 'verifikasis',
            'keputusan_verifikasis', 'draf_tanggapans', 'draf_laporans', 'tindak_lanjuts', 'draf_historis'];

        foreach ($tabel as $t) {
            if (! Schema::hasTable($t)) {
                continue;
            }
            $this->assertSame(0, DB::table($t)->count(), "Tabel {$t} masih berisi.");
        }
    }

    public function test_layar_tetap_terbuka_sesudah_dikosongkan(): void
    {
        Artisan::call('tlhp:kosongkan', ['--force' => true]);

        $this->masuk('setba@contoh.test');
        $this->get('/rekomendasi')->assertOk()->assertSee('Belum ada rekomendasi');
        $this->get('/laporan')->assertOk();
        $this->get('/ringkasan')->assertOk();
        $this->get('/pemberitahuan')->assertOk()->assertSee('Belum ada pemberitahuan');
    }
}
