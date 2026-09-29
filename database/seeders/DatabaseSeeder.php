<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DataMasterSeeder::class);
        /* Hitungan bingkai yang tersimpan di cache milik data sebelum disemai
           ulang — dibuat basi (27 Sep). */
        \App\Support\Rangka::dataBerubah();

        /* Padanan `?kosong` di prototipe: sistem tanpa data contoh, dengan
           data master dan akun yang tetap ada. Pasang
           SIMTLHP_DATA_CONTOH=false sebelum menyemai. */
        if (config('simtlhp.data_contoh')) {
            $this->call(DataContohSeeder::class);

            /* Prototipe menjalankan penyapu kiriman otomatis sekali saat
               dibuka. Tanpa ini, data contoh yang baru disemai berbeda satu
               berkas dan satu pemberitahuan dari prototipenya. */
            \Illuminate\Support\Facades\Artisan::call('tlhp:kirim-draf');
        }
    }
}
