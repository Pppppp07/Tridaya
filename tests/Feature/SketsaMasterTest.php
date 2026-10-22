<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/** Alamat yang disimpan tetap membuka daftar, filter, dan formulir dari server tanpa JavaScript. */
class SketsaMasterTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    public function test_semua_tab_dan_jendela_terbuka(): void
    {
        $this->siapkanDataContoh();
        $this->masuk('admin@contoh.test');
        $alamat = [
            ['tab' => 'ringkasan'], ['tab' => 'pengguna'], ['tab' => 'peran'], ['tab' => 'unit'],
            ['tab' => 'temuan'], ['tab' => 'temuan', 'filter' => 'LHA'], ['tab' => 'intern'], ['tab' => 'sifat'], ['tab' => 'riwayat'],
            ['tab' => 'pengguna', 'peran' => 'uki', 'masuk' => 'sepi', 'urut' => 'nama', 'arah' => 'turun', 'cari' => 'a'],
            ['tab' => 'pengguna', 'jendela' => 'pengguna'], ['tab' => 'pengguna', 'jendela' => 'impor'],
            ['tab' => 'pengguna', 'jendela' => 'detail-1'], ['tab' => 'pengguna', 'jendela' => 'akun-2'],
            ['tab' => 'unit', 'jendela' => 'unit'], ['tab' => 'unit', 'jendela' => 'riwayat-1'], ['pj' => 1],
            ['tab' => 'temuan', 'jendela' => 'temuan'], ['tab' => 'temuan', 'jendela' => 'banyak-temuan'],
            ['tab' => 'temuan', 'jendela' => 'gabung-temuan-1'], ['tab' => 'intern', 'jendela' => 'gabung-intern-1'],
            ['tab' => 'sifat', 'jendela' => 'sifat'], ['tab' => 'sifat', 'jendela' => 'gabung-sifat-1'], ['tab' => 'sifat', 'jendela' => 'banyak-sifat'],
            ['tab' => 'riwayat', 'rentang' => '7', 'filter' => 'unit'],
        ];
        foreach ($alamat as $a) {
            $r = $this->get(route('master', $a));
            $r->assertOk();
            $r->assertSee('data-master-akar', false);
        }
    }
}
