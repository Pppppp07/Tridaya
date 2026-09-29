<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\LogAktivitas;
use App\Models\User;
use App\Support\DirektoriIrm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as PermintaanHttp;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Kesiapan SSO eHRM (27 Sep). SSO membuktikan siapa orangnya; Data master
 * yang memutus boleh tidaknya ia masuk, dan sebagai apa — menurut NIP.
 */
class SsoTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    private function nip(string $nama): string
    {
        return DirektoriIrm::semua()->firstWhere('nama', $nama)['nip'];
    }

    public function test_sso_simulasi_memasukkan_pegawai_terdaftar_menurut_nip(): void
    {
        config(['simtlhp.sso.driver' => 'simulasi']);
        $this->get(route('masuk'))->assertOk()->assertSee('Masuk dengan SSO PU');
        $this->get(route('sso.arahkan'))->assertRedirect(route('sso.simulasi'));
        $this->get(route('sso.simulasi'))->assertOk()->assertSee('Contoh cepat')->assertSee('belum terdaftar');

        $this->post(route('sso.simulasi.pilih'), ['nip' => $this->nip('Sri Wahyuni')])->assertRedirect(route('beranda'));
        $setba = User::where('email', 'setba@contoh.test')->firstOrFail();
        $this->assertAuthenticatedAs($setba);
        $this->assertTrue(session('masuk_lewat_sso'));
        $log = LogAktivitas::where('aksi', 'masuk')->latest('id')->firstOrFail();
        $this->assertSame([$setba->id, 'sso'], [$log->user_id, $log->rincian['cara']]);
    }

    public function test_pegawai_tanpa_akun_atau_yang_dinonaktifkan_ditolak_dan_tercatat(): void
    {
        config(['simtlhp.sso.driver' => 'simulasi']);

        /* Pesan penolakan (29 Sep, kata Hizkia): "anda tidak memiliki akses
           untuk sistem ini, hubungi Admin atau pihak berwenang atas sistem ini". */
        $this->post(route('sso.simulasi.pilih'), ['nip' => $this->nip('Siti Rahmawati')])
            ->assertForbidden()->assertSee('Tidak memiliki akses')
            ->assertSee('Anda tidak memiliki akses untuk sistem ini. Hubungi Admin atau pihak berwenang atas sistem ini.')
            ->assertSee('belum terdaftar')->assertSee('Siti Rahmawati');
        $this->assertGuest();
        $log = LogAktivitas::where('aksi', 'masuk.sso.ditolak')->latest('id')->firstOrFail();
        $this->assertStringContainsString('Siti Rahmawati', $log->nama);
        $this->assertNull($log->user_id);

        User::where('email', 'uki@contoh.test')->update(['aktif' => false]);
        $this->post(route('sso.simulasi.pilih'), ['nip' => $this->nip('Teguh Santoso')])
            ->assertForbidden()->assertSee('Anda tidak memiliki akses untuk sistem ini.')->assertSee('sudah dinonaktifkan');
        $this->assertGuest();
    }

    public function test_simulasi_tidak_pernah_menyala_di_produksi(): void
    {
        config(['simtlhp.sso.driver' => 'simulasi']);
        $this->app['env'] = 'production';

        $this->get(route('sso.simulasi'))->assertNotFound();
        $this->get(route('sso.arahkan'))->assertNotFound();
        $this->get(route('masuk'))->assertOk()->assertDontSee('Masuk dengan SSO');
        $this->assertGuest();
    }

    private function oidc(): void
    {
        config(['simtlhp.sso.driver' => 'oidc', 'simtlhp.sso.oidc' => array_merge(config('simtlhp.sso.oidc'), [
            'authorize' => 'https://sso.contoh.test/auth', 'token' => 'https://sso.contoh.test/token',
            'userinfo' => 'https://sso.contoh.test/userinfo', 'client_id' => 'simtlhp', 'client_secret' => 'rahasia-klien',
        ])]);
    }

    /** @return array{0:string,1:array} alamat tujuan dan isi kuerinya */
    private function mulaiOidc(): array
    {
        $ke = $this->get(route('sso.arahkan'))->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($ke, PHP_URL_QUERY), $q);

        return [$ke, $q];
    }

    public function test_oidc_lengkap_dengan_state_pkce_dan_penyamaan_atribut(): void
    {
        $this->oidc();
        [$ke, $q] = $this->mulaiOidc();
        $this->assertStringStartsWith('https://sso.contoh.test/auth?', $ke);
        $this->assertSame(['code', 'simtlhp', 'S256', route('sso.kembali')],
            [$q['response_type'], $q['client_id'], $q['code_challenge_method'], $q['redirect_uri']]);
        $this->assertNotEmpty($q['state']);
        $this->assertNotEmpty($q['code_challenge']);
        $this->assertArrayNotHasKey('client_secret', $q, 'Rahasia klien tidak boleh lewat browser.');

        Http::fake([
            'sso.contoh.test/token' => Http::response(['access_token' => 'token-akses', 'token_type' => 'Bearer']),
            'sso.contoh.test/userinfo' => Http::response(['nip' => '19780125 200501 2 005', 'name' => 'Sri Wahyuni',
                'email' => 'setba@contoh.test', 'jabatan' => 'Kepala Subbagian Tindak Lanjut']),
        ]);
        $this->get(route('sso.kembali', ['code' => 'kode-sekali-pakai', 'state' => $q['state']]))->assertRedirect(route('beranda'));

        $setba = User::where('email', 'setba@contoh.test')->firstOrFail();
        $this->assertAuthenticatedAs($setba);
        /* Kode ditukar lewat jalur belakang, dengan verifier PKCE-nya. */
        Http::assertSent(fn (PermintaanHttp $r) => $r->url() === 'https://sso.contoh.test/token'
            && $r['grant_type'] === 'authorization_code' && $r['code'] === 'kode-sekali-pakai'
            && rtrim(strtr(base64_encode(hash('sha256', $r['code_verifier'], true)), '+/', '-_'), '=') === $q['code_challenge']);
        Http::assertSent(fn (PermintaanHttp $r) => $r->url() === 'https://sso.contoh.test/userinfo'
            && $r->hasHeader('Authorization', 'Bearer token-akses'));
        /* Jabatan disamakan dengan eHRM, dan perubahannya tercatat. */
        $this->assertSame('Kepala Subbagian Tindak Lanjut', $setba->jabatan);
        $this->assertTrue(LogAktivitas::where('aksi', 'masuk.sso.samakan')->where('user_id', $setba->id)->exists());
    }

    public function test_oidc_menolak_state_palsu_dan_identitas_tanpa_nip(): void
    {
        $this->oidc();
        Http::fake([
            'sso.contoh.test/token' => Http::response(['access_token' => 'token-akses']),
            'sso.contoh.test/userinfo' => Http::response(['name' => 'Tanpa NIP', 'email' => 'x@contoh.test']),
        ]);

        $this->mulaiOidc();
        /* Kegagalan teknis bukan "tidak punya akses" — orangnya belum tentu tidak berhak. */
        $this->get(route('sso.kembali', ['code' => 'x', 'state' => 'palsu']))->assertForbidden()->assertSee('tidak valid')
            ->assertSee('Gagal masuk')->assertDontSee('Anda tidak memiliki akses untuk sistem ini');
        Http::assertNothingSent();

        /* State sekali pakai: yang sudah dipakai tidak bisa diulang. */
        [, $q] = $this->mulaiOidc();
        $this->get(route('sso.kembali', ['code' => 'x', 'state' => $q['state']]))->assertForbidden()->assertSee('tidak membawa NIP');
        $this->get(route('sso.kembali', ['code' => 'x', 'state' => $q['state']]))->assertForbidden()->assertSee('tidak valid');
        $this->assertGuest();
        $this->assertSame(3, LogAktivitas::where('aksi', 'masuk.sso.ditolak')->count());
    }

    public function test_direktori_ehrm_lewat_http_dipetakan_dan_errornya_terbaca(): void
    {
        config(['simtlhp.direktori.driver' => 'http', 'simtlhp.direktori.http' => array_merge(config('simtlhp.direktori.http'), [
            'url' => 'https://ehrm.contoh.test/api', 'token' => 'token-ehrm', 'simpan_menit' => 0,
        ])]);
        DirektoriIrm::lupakan();
        $baris = ['nip' => '198001012005011001', 'nama' => 'Ahmad Pegawai', 'jabatan' => 'Analis',
            'kode_unit' => 'BALAI-4-BANDUNG', 'nama_unit' => 'Balai Bandung', 'email' => 'AHMAD@pu.go.id'];
        Http::fake([
            'ehrm.contoh.test/api/pegawai/198001012005011001' => Http::response(['data' => $baris]),
            'ehrm.contoh.test/api/pegawai?q=ahmad' => Http::response(['data' => [$baris]]),
            'ehrm.contoh.test/api/unit/*' => Http::response(['data' => []]),
        ]);

        $hasil = DirektoriIrm::cari('ahmad');
        $this->assertSame([['nip' => '198001012005011001', 'nama' => 'Ahmad Pegawai', 'jabatan' => 'Analis',
            'unit' => 'BALAI-4-BANDUNG', 'unitNama' => 'Balai Bandung', 'email' => 'ahmad@pu.go.id', 'pj' => false]], $hasil->all());
        $this->assertSame('Ahmad Pegawai', DirektoriIrm::nip('19800101 200501 1 001')['nama']);
        Http::assertSent(fn (PermintaanHttp $r) => $r->hasHeader('Authorization', 'Bearer token-ehrm'));

        /* Pemalsu yang lama tetap berlaku untuk "ahmad" — yang gagal "budi". */
        Http::fake(['ehrm.contoh.test/*' => Http::response('rusak', 500)]);
        DirektoriIrm::lupakan();
        $this->assertTrue(DirektoriIrm::cari('budi')->isEmpty());
        $this->assertStringContainsString('error (500)', (string) DirektoriIrm::error());
    }

    public function test_perintah_server_untuk_akun_pertama_dan_penghubungan(): void
    {
        $dewi = DirektoriIrm::semua()->firstWhere('nama', 'Dewi Kartikasari');
        $this->assertSame(0, Artisan::call('simtlhp:pengguna', ['nip' => $dewi['nip'], 'peran' => 'admin']));
        $u = User::where('nip', $dewi['nip'])->firstOrFail();
        $this->assertSame([PeranPengguna::ADMIN, $dewi['email'], true], [$u->peran, $u->email, $u->aktif]);
        $this->assertTrue(LogAktivitas::where('aksi', 'master.pengguna.tambah')->where('subjek_id', $u->id)->exists());

        /* Penanggung jawab unit kerja tidak ditetapkan dari sini. */
        $pj = User::where('peran', 'satker')->where('aktif', true)->firstOrFail();
        $this->assertSame(1, Artisan::call('simtlhp:pengguna', ['nip' => $pj->nip, 'peran' => 'uki']));

        /* Akun lama tanpa NIP dihubungkan menurut email-nya. */
        User::where('email', 'pimpinan@contoh.test')->update(['nip' => null]);
        $this->assertSame(0, Artisan::call('simtlhp:hubungkan-pegawai'));
        $this->assertSame($this->nip('Bambang Hermawan'), User::where('email', 'pimpinan@contoh.test')->value('nip'));
    }
}
