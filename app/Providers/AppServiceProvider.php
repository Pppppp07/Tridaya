<?php

namespace App\Providers;

use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /* Tanggal demo, lihat config/simtlhp.php. Hanya tanggalnya yang
           dipatok — jam pemberitahuan tetap berjalan. Uji otomatis mengatur waktunya
           sendiri, jadi tidak disentuh. */
        $hari = config('simtlhp.hari_ini');
        if ($hari && ! $this->app->runningUnitTests()) {
            $kini = Carbon::now();
            Carbon::setTestNow(Carbon::parse($hari)->setTime($kini->hour, $kini->minute, $kini->second));

            /* Jam demo mundur sehari tiap lewat tengah malam sungguhan: yang
               disimpan cache menjelang tengah malam lalu tampak berlaku sampai
               tengah malam berikutnya. 28 Sep hitungan pembatas masuk (30 kali
               semenit per alamat) jadi menumpuk seharian, dan masuk demo
               ditolak "Too Many Requests". Maka begitu hari sungguhannya
               berganti, cache dikosongkan sekali — isinya hanya hitungan yang
               bisa dihitung ulang. Pemasangan sungguhan tidak mematok tanggal,
               jadi tidak tersentuh. */
            try {
                if (\Illuminate\Support\Facades\Cache::get('simtlhp:hari-nyata') !== $kini->toDateString()) {
                    \Illuminate\Support\Facades\Cache::flush();
                    \Illuminate\Support\Facades\Cache::forever('simtlhp:hari-nyata', $kini->toDateString());
                }
            } catch (\Throwable $e) {
                /* Cache belum siap — misalnya sebelum migrasi pertama. */
            }
        }
    }
}
