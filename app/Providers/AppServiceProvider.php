<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /* Batas per user: tulis dan baca dihitung terpisah, supaya membaca tidak menghabiskan jatah
           menyimpan. Penolakannya pesan "tunggu sebentar" (bootstrap/app.php). */
        RateLimiter::for('pengguna', function (Request $r) {
            $kunci = $r->user()?->id ?: $r->ip();

            return $r->isMethodSafe()
                ? Limit::perMinute(max(1, (int) config('simtlhp.beban.baca_per_menit')))->by('baca:'.$kunci)
                : Limit::perMinute(max(1, (int) config('simtlhp.beban.tulis_per_menit')))->by('tulis:'.$kunci);
        });
        RateLimiter::for('masuk-ip', fn (Request $r) => Limit::perMinute(max(1, (int) config('simtlhp.masuk.per_menit_ip')))
            ->by('masuk:'.$r->ip()));
        // Pengecekan banyak user dapat datang dari satu server SuperApp.
        RateLimiter::for('dwaradaya-check', fn (Request $r) => Limit::perMinute(max(1, (int) config('simtlhp.sso.dwaradaya.cek_per_menit', 600)))
            ->by('dwaradaya:'.$r->ip()));
        RateLimiter::for('unduh', fn (Request $r) => Limit::perMinute(max(1, (int) config('simtlhp.beban.unduh_per_menit')))
            ->by('unduh:'.($r->user()?->id ?: $r->ip())));
        /* Email keluar: satu batas untuk seluruh pekerja antrean. */
        RateLimiter::for('email-keluar', fn () => Limit::perMinute(max(1, (int) config('simtlhp.beban.email_per_menit'))));

        /* Tanggal demo (config simtlhp.hari_ini): hanya tanggalnya yang dipatok, jamnya tetap
           berjalan. Uji mengatur waktunya sendiri. */
        $hari = config('simtlhp.hari_ini');
        if ($hari && ! $this->app->runningUnitTests()) {
            $kini = Carbon::now();
            Carbon::setTestNow(Carbon::parse($hari)->setTime($kini->hour, $kini->minute, $kini->second));

            /* Jam demo mundur sehari tiap lewat tengah malam, jadi cache dikosongkan sekali tiap
               hari baru (supaya hitungan pembatas tidak menumpuk). */
            try {
                if (Cache::get('simtlhp:hari-nyata') !== $kini->toDateString()) {
                    Cache::flush();
                    Cache::forever('simtlhp:hari-nyata', $kini->toDateString());
                }
            } catch (\Throwable $e) {
                /* Cache belum siap — misalnya sebelum migrasi pertama. */
            }
        }
    }
}
