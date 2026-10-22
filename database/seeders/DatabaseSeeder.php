<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DataMasterSeeder::class);
        /* Hitungan bingkai di cache dibuat basi. */
        \App\Support\Rangka::dataBerubah();

        /* SIMTLHP_DATA_CONTOH=false: tanpa data contoh (master dan akun tetap ada). */
        if (config('simtlhp.data_contoh')) {
            $this->call(DataContohSeeder::class);
        }
    }
}
