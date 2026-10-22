<?php

namespace Tests\Unit;

use App\Http\Controllers\MasukController;
use App\Models\User;
use App\Support\Akun;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoProduksiTest extends TestCase
{
    public function test_production_menolak_login_developer_meski_setting_demo_menyala(): void
    {
        $this->app['env'] = 'production';
        config(['simtlhp.akun_demo' => true, 'simtlhp.masuk.password' => true]);

        $this->assertFalse(MasukController::adaPengembang());
        $this->get(route('masuk.pengembang'))->assertNotFound();
        $this->get(route('masuk'))->assertOk()->assertDontSee('Login developer')->assertDontSee('data-email=', false);
    }

    public function test_password_demo_diabaikan_pada_akun_baru_di_production(): void
    {
        $this->app['env'] = 'production';
        config(['simtlhp.password_demo' => 'password-demo-uji']);

        $a = Akun::passwordAwal();
        $b = Akun::passwordAwal();
        $this->assertSame(40, strlen($a));
        $this->assertNotSame('password-demo-uji', $a);
        $this->assertNotSame($a, $b);
        $u = new User(['password' => $a]);
        $this->assertTrue(Hash::check($a, $u->password));
        $this->assertFalse(Hash::check('password-demo-uji', $u->password));
    }

    public function test_setting_demo_tetap_berfungsi_di_luar_production(): void
    {
        $this->app['env'] = 'local';
        config(['simtlhp.password_demo' => 'password-demo-uji', 'simtlhp.akun_demo' => true, 'simtlhp.masuk.password' => true]);

        $this->assertSame('password-demo-uji', Akun::passwordAwal());
        $this->assertTrue(MasukController::adaPengembang());
        config(['simtlhp.password_demo' => null, 'simtlhp.akun_demo' => false]);
        $this->assertSame(40, strlen(Akun::passwordAwal()));
        $this->assertFalse(MasukController::adaPengembang());
    }
}
