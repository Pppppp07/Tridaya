<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Tanggal demo mematok jam aplikasi di masa lalu, tapi cookie tetap harus
 * berumur menurut jam sungguhan (27 Sep). Tanpa itu cookie sesi tiba sudah
 * kedaluwarsa, tiap halaman membuka sesi baru, formulir ditolak 419, dan
 * pop-up pemberitahuan tampil lagi di tiap halaman. Lihat UmurCookieNyata.
 */
class UmurCookieTest extends TestCase
{
    use RefreshDatabase;

    private function cookieDari(string $url): array
    {
        return collect($this->get($url)->assertOk()->headers->getCookies())
            ->keyBy(fn ($c) => $c->getName())->all();
    }

    public function test_cookie_berumur_menurut_jam_sungguhan_walau_tanggal_dipatok(): void
    {
        Carbon::setTestNow('2026-08-17 10:00:00');
        $cookie = $this->cookieDari(route('masuk'));

        foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $nama) {
            $this->assertArrayHasKey($nama, $cookie);
            $this->assertEqualsWithDelta(time() + config('session.lifetime') * 60, $cookie[$nama]->getExpiresTime(), 5, $nama);
            $this->assertGreaterThan(0, $cookie[$nama]->getMaxAge(), $nama);
        }
    }

    public function test_tanpa_tanggal_dipatok_umurnya_tidak_diubah(): void
    {
        Carbon::setTestNow();
        $sesi = $this->cookieDari(route('masuk'))[config('session.cookie')];

        $this->assertEqualsWithDelta(time() + config('session.lifetime') * 60, $sesi->getExpiresTime(), 5);
    }
}
