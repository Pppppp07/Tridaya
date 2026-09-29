<?php

namespace Tests;

use App\Support\DirektoriIrm;
use App\Support\Rangka;
use App\Support\Terlihat;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Bingkai halaman menyimpan hitungannya dalam peubah statis, dan statis
     * bertahan selama PROSES — bukan selama permintaan. Satu proses uji
     * menjalankan ratusan permintaan, jadi tanpa dilepas di sini halaman
     * menggambar angka milik uji sebelumnya.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Rangka::lupakan();
        Terlihat::lupakan();
        DirektoriIrm::lupakan();
    }
}
