<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Notifikasi;
use App\Models\Rekomendasi;
use App\Notifications\PemberitahuanEmail;
use App\Support\Pemberitahuan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/** Penjaga beban untuk server terbatas: batas per user, antrean email, daftar yang dibatasi. */
class BebanServerTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    private const AJAX = ['X-Requested-With' => 'XMLHttpRequest'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    public function test_batas_tulis_per_user_memberi_pesan_bukan_halaman_error(): void
    {
        config(['simtlhp.beban.tulis_per_menit' => 2]);
        $setba = $this->akun('setba@contoh.test');
        $asal = route('pemberitahuan');

        $this->actingAs($setba)->from($asal)->post(route('pemberitahuan.semua'))->assertRedirect($asal);
        $this->actingAs($setba)->from($asal)->post(route('pemberitahuan.semua'))->assertRedirect($asal);
        $this->actingAs($setba)->from($asal)->post(route('pemberitahuan.semua'))
            ->assertRedirect($asal)
            ->assertSessionHas('gagal', 'Terlalu banyak permintaan dalam waktu singkat. Tunggu sebentar, lalu coba lagi.');

        // Skrip mendapat 429 apa adanya; jatah baca terpisah dari jatah tulis.
        $this->actingAs($setba)->postJson(route('pemberitahuan.semua'))->assertStatus(429);
        $this->actingAs($setba)->get($asal)->assertOk();
    }

    public function test_batas_tulis_dihitung_per_user(): void
    {
        config(['simtlhp.beban.tulis_per_menit' => 1]);
        $setba = $this->akun('setba@contoh.test');
        $medan = $this->akun('medan');

        $this->actingAs($setba)->withHeaders(self::AJAX)->postJson(route('pemberitahuan.semua'))->assertOk();
        $this->actingAs($setba)->postJson(route('pemberitahuan.semua'))->assertStatus(429);
        $this->actingAs($medan)->withHeaders(self::AJAX)->postJson(route('pemberitahuan.semua'))->assertOk();
    }

    public function test_batas_masuk_per_ip_bisa_diatur_dan_memberi_pesan(): void
    {
        config(['simtlhp.masuk.per_menit_ip' => 2]);
        foreach (['a@contoh.test', 'b@contoh.test'] as $email) {
            $this->post('/masuk', ['email' => $email, 'password' => 'salah'])->assertRedirect();
        }

        $this->post('/masuk', ['email' => 'c@contoh.test', 'password' => 'salah'])
            ->assertRedirect(route('masuk'))
            ->assertSessionHasErrors(['email' => 'Terlalu banyak percobaan masuk dari jaringan ini. Tunggu sebentar, lalu coba lagi.']);
        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_unduhan_dibatasi_terpisah_dari_halaman_biasa(): void
    {
        config(['simtlhp.beban.unduh_per_menit' => 1]);
        $dti = $this->akun('dti@contoh.test');

        $this->actingAs($dti)->get(route('log.unduh'))->assertOk();
        $this->actingAs($dti)->from(route('log'))->get(route('log.unduh'))
            ->assertRedirect(route('log'))->assertSessionHas('gagal');
        $this->actingAs($dti)->get(route('log'))->assertOk();
    }

    public function test_email_pemberitahuan_dititipkan_ke_antrean_email(): void
    {
        config(['queue.default' => 'database']);
        Queue::fake();
        $r = Rekomendasi::first();

        Pemberitahuan::tulis($r, 'Rekomendasi baru untuk satuan kerja', [PeranPengguna::SATKER]);

        Queue::assertPushedOn('email', SendQueuedNotifications::class,
            fn ($job) => $job->notification instanceof PemberitahuanEmail);
    }

    public function test_batas_laju_email_hanya_di_pekerja_antrean(): void
    {
        $n = Notifikasi::first();
        $u = $this->akun('medan');

        $antre = (new PemberitahuanEmail($n))->onConnection('database');
        $this->assertInstanceOf(RateLimited::class, $antre->middleware($u, 'mail')[0]);
        // Tanpa antrean, pekerjaan yang ditahan tidak dikembalikan: emailnya bisa hilang.
        $this->assertSame([], (new PemberitahuanEmail($n))->onConnection('sync')->middleware($u, 'mail'));
        $this->assertSame('email', $antre->queue);
    }

    public function test_panel_dan_halaman_memuat_yang_terbaru_saja(): void
    {
        $setba = $this->akun('setba@contoh.test');
        $this->buatPemberitahuan(Pemberitahuan::BATAS_HALAMAN + 5);
        $total = Pemberitahuan::belumDibaca($setba);

        $d = Pemberitahuan::daftarTerbatas($setba, Pemberitahuan::BATAS_PANEL);
        $this->assertCount(Pemberitahuan::BATAS_PANEL, $d['semuaId']);
        $this->assertLessThanOrEqual(Pemberitahuan::BATAS_PANEL * 2, $d['daftar']->count());
        // Angkanya tetap angka seluruhnya.
        $this->assertSame($total, $d['belum']);

        $this->actingAs($setba)->get(route('pemberitahuan', ['tab' => 'semua']))->assertOk()
            ->assertSee('Tampil '.Pemberitahuan::BATAS_HALAMAN.' pemberitahuan terbaru.');
    }

    public function test_tandai_semua_dibaca_sekaligus_termasuk_yang_tidak_tampil(): void
    {
        $setba = $this->akun('setba@contoh.test');
        $this->buatPemberitahuan(Pemberitahuan::BATAS_HALAMAN + 5);
        $this->assertGreaterThan(Pemberitahuan::BATAS_HALAMAN, Pemberitahuan::belumDibaca($setba));

        $this->actingAs($setba)->withHeaders(self::AJAX)->postJson(route('pemberitahuan.semua'))->assertOk()->assertJson(['belum' => 0]);
        $this->assertSame(0, Pemberitahuan::belumDibaca($setba));
        // Diulang tidak menggandakan tanda baca.
        $sebelum = DB::table('notifikasi_bacas')->count();
        Pemberitahuan::tandaiSemua($setba);
        $this->assertSame($sebelum, DB::table('notifikasi_bacas')->count());
    }

    private function buatPemberitahuan(int $jumlah): void
    {
        $r = Rekomendasi::first();
        $waktu = now()->subDays(30);
        Notifikasi::insert(collect(range(1, $jumlah))->map(fn ($i) => [
            'rekomendasi_id' => $r->id, 'waktu' => $waktu->copy()->addMinutes($i),
            'label_pelaku' => 'Itjen', 'aksi' => 'Berkas diteruskan '.$i,
            'untuk_peran' => json_encode(['setba']),
        ])->all());
    }
}
