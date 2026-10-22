<?php

namespace Tests;

use App\Support\DirektoriIrm;
use App\Support\Rangka;
use App\Support\Terlihat;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Hitungan bingkai disimpan di peubah statis yang bertahan selama proses; dilepas tiap uji. */
    protected function setUp(): void
    {
        parent::setUp();
        Rangka::lupakan();
        Terlihat::lupakan();
        DirektoriIrm::lupakan();
    }
}
