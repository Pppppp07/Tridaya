<?php

namespace Tests\Feature;

use App\Models\Lampiran;
use App\Models\LogAktivitas;
use App\Models\SumberBukti;
use App\Models\User;
use App\Rules\LinkBukti;
use App\Support\KebijakanBukti;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LinkBuktiTest extends TestCase
{
    use RefreshDatabase;

    private const FILE = '1AbcDEFghiJKLmnoPQRstUVwxyZ';

    protected function setUp(): void
    {
        parent::setUp();
        KebijakanBukti::lupakan();
    }

    public static function tautan(): array
    {
        $id = self::FILE;

        return [
            'drive-file' => ["https://drive.google.com/file/d/$id/view?usp=sharing", true],
            'drive-open' => ["https://drive.google.com/open?id=$id", true],
            'drive-uc' => ["https://drive.google.com/uc?id=$id&export=download", true],
            'docs' => ["https://docs.google.com/document/d/$id/edit", true],
            'sheets' => ["https://docs.google.com/spreadsheets/d/$id/edit#gid=12", true],
            'slides' => ["https://docs.google.com/presentation/d/$id/preview", true],
            'resourcekey' => ["https://drive.google.com/file/d/$id/view?resourcekey=0-Abc123", true],
            'uppercase-host' => ["HTTPS://DRIVE.GOOGLE.COM/file/d/$id/view", true],
            'javascript' => ['javascript:alert(1)', false],
            'data' => ['data:text/html,<script>alert(1)</script>', false],
            'http' => ["http://drive.google.com/file/d/$id/view", false],
            'fake-domain' => ["https://drive.google.com.evil.test/file/d/$id/view", false],
            'credentials' => ["https://drive.google.com@evil.test/file/d/$id/view", false],
            'trailing-dot' => ["https://drive.google.com./file/d/$id/view", false],
            'shortlink' => ['https://bit.ly/abc123', false],
            'website' => ['https://contoh.test/berita', false],
            'video-platform' => ['https://www.youtube.com/watch?v=abc123', false],
            'adult-website' => ['https://video-tidak-relevan.test/video/123', false],
            'drive-folder' => ["https://drive.google.com/drive/folders/$id", false],
            'drive-home' => ['https://drive.google.com/', false],
            'google-search' => ['https://www.google.com/search?q=bukti', false],
            'google-redirect' => ['https://docs.google.com/url?url=https://evil.test', false],
            'forms' => ["https://docs.google.com/forms/d/$id/viewform", false],
            'redirect-param' => ["https://drive.google.com/file/d/$id/view?redirect=https://evil.test", false],
            'nested-param' => ["https://drive.google.com/open?id[]=$id", false],
            'relative' => ["//drive.google.com/file/d/$id/view", false],
            'custom-port' => ["https://drive.google.com:8443/file/d/$id/view", false],
            'loopback' => ["https://127.0.0.1/file/d/$id/view", false],
            'metadata-ip' => ['https://169.254.169.254/latest/meta-data', false],
            'ipv6' => ['https://[::1]/bukti.pdf', false],
            'path-traversal' => ["https://drive.google.com/file/d/../$id/view", false],
            'encoded-path' => ["https://drive.google.com/file/d/%252e%252e/$id/view", false],
            'control' => ["https://drive.google.com/file/d/$id/view\n", true],
        ];
    }

    #[DataProvider('tautan')]
    public function test_kebijakan_link(string $link, bool $boleh): void
    {
        Http::fake([]);
        $this->assertSame($boleh, KebijakanBukti::sah($link), $link);
        Http::assertNothingSent();
    }

    public function test_link_google_dibakukan_ke_file_dan_kunci_akses_dipertahankan(): void
    {
        $hasil = KebijakanBukti::periksa('https://drive.google.com/uc?id='.self::FILE.'&export=download&resourcekey=0-Abc123');
        $this->assertSame('https://drive.google.com/file/d/'.self::FILE.'/view?resourcekey=0-Abc123', $hasil['url']);
    }

    public function test_gateway_tidak_menerima_tujuan_bebas_dan_memeriksa_aturan_terbaru(): void
    {
        $this->actingAs(User::factory()->create(['peran' => 'setba', 'aktif' => true]));
        $pintu = KebijakanBukti::pintu('https://drive.google.com/file/d/'.self::FILE.'/view');
        $this->get($pintu)->assertRedirect('https://drive.google.com/file/d/'.self::FILE.'/view')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get(route('berkas.link', ['tujuan' => 'https://evil.test']))->assertForbidden();
        $this->get($pintu.'&tambahan=1')->assertForbidden();
        SumberBukti::where('host', 'drive.google.com')->update(['aktif' => false]);
        KebijakanBukti::lupakan();
        $this->get($pintu)->assertStatus(422)->assertHeaderMissing('Location');
    }

    public function test_gateway_lampiran_tidak_membuka_bukti_yang_ditolak(): void
    {
        $u = User::factory()->create(['peran' => 'setba', 'aktif' => true]);
        $b = Lampiran::create(['nama_asli' => 'Bukti', 'link' => 'https://drive.google.com/file/d/'.self::FILE.'/view',
            'ditolak_pada' => now(), 'ditolak_oleh' => $u->id, 'alasan_penolakan' => 'Isi bukan bukti yang diminta.']);
        $this->actingAs($u)->get(route('berkas.buka', $b))->assertStatus(422);
        $this->get(route('berkas.show', $b))->assertOk()->assertSee('Bukti ditolak')->assertDontSee('target="_blank"', false);
        $this->assertSame('Isi bukan bukti yang diminta.', $b->fresh()->alasan_penolakan);
    }

    public function test_link_lama_website_umum_tetap_terarsip_tanpa_tombol_buka(): void
    {
        $b = Lampiran::create(['nama_asli' => 'Bukti lama', 'link' => 'https://contoh.test/video']);
        $u = User::factory()->create(['peran' => 'setba', 'aktif' => true]);
        $this->actingAs($u)->get(route('berkas.show', $b))->assertOk()->assertSee('Perlu diperbarui');
        $this->get(route('berkas.buka', $b))->assertStatus(422);
        $html = (string) $this->blade('<x-berkas :b="$b" />', ['b' => $b]);
        $this->assertStringContainsString('Perlu diperbarui', $html);
        preg_match("/data-preview='([^']+)'/", $html, $m);
        $this->assertSame('', json_decode($m[1], true)['buka']);
        $this->assertSame('https://contoh.test/video', $b->fresh()->link);
    }

    public function test_hanya_admin_dti_aktif_yang_bisa_mengatur_sumber(): void
    {
        foreach (['uki', 'setba', 'inspektorat', 'dti'] as $peran) {
            $this->actingAs(User::factory()->create(['peran' => $peran, 'aktif' => true]))
                ->post(route('master.sumber.tambah'), ['nama' => 'Arsip', 'host' => 'arsip.instansi.go.id', 'prefix' => '/dokumen/', 'aktif' => 1])
                ->assertForbidden();
        }
        $this->assertDatabaseCount('sumber_bukti', 2);
        $this->actingAs(User::factory()->create(['peran' => 'admin', 'aktif' => true]));
        $this->get(route('master', ['tab' => 'sumber']))->assertOk()->assertSee('Sumber link bukti')->assertSee('Google Drive');
        $this->post(route('master.sumber.tambah'), ['nama' => 'Arsip', 'host' => 'Arsip.Instansi.Go.Id', 'prefix' => '/dokumen/', 'aktif' => 1])
            ->assertRedirect(route('master', ['tab' => 'sumber']));
        $this->assertDatabaseHas('sumber_bukti', ['host' => 'arsip.instansi.go.id']);
        KebijakanBukti::lupakan();
        $this->assertTrue(KebijakanBukti::sah('https://arsip.instansi.go.id/dokumen/bukti.pdf'));
        $this->assertFalse(KebijakanBukti::sah('https://arsip.instansi.go.id/berita/123'));
        $this->assertFalse(KebijakanBukti::sah('https://arsip.instansi.go.id/dokumen/video.mp4'));
        $this->assertFalse(KebijakanBukti::sah('https://arsip.instansi.go.id/dokumen/bukti.exe'));
        $this->assertFalse(KebijakanBukti::sah('https://arsip.instansi.go.id/dokumen/bukti.pdf?redirect=https://evil.test'));
        $this->assertDatabaseHas('log_aktivitas', ['aksi' => 'master.sumber_bukti.simpan']);
    }

    public function test_setting_sumber_menolak_wildcard_ip_dan_jalur_website_umum(): void
    {
        $this->actingAs(User::factory()->create(['peran' => 'admin', 'aktif' => true]));
        foreach (['*.google.com', '127.0.0.1', 'https://arsip.instansi.go.id', 'arsip.instansi.go.id/path'] as $host) {
            $this->post(route('master.sumber.tambah'), ['nama' => 'Arsip', 'host' => $host, 'prefix' => '/dokumen/', 'aktif' => 1])
                ->assertSessionHasErrors('host');
        }
        $this->post(route('master.sumber.tambah'), ['nama' => 'Arsip', 'host' => 'arsip.instansi.go.id', 'prefix' => '/', 'aktif' => 1])
            ->assertSessionHasErrors('prefix');
        $this->assertDatabaseCount('sumber_bukti', 2);
    }

    public function test_penolakan_link_tidak_mencatat_token_dan_satu_request_tidak_menggandakan_log(): void
    {
        Route::post('/uji-link-bukti', function (Request $req) {
            $req->validate(['bukti.*.link' => ['required', new LinkBukti]]);

            return response('ok');
        })->middleware('web')->name('uji.link.bukti');
        $u = User::factory()->create(['peran' => 'setba', 'aktif' => true]);
        $this->actingAs($u)->postJson('/uji-link-bukti', ['bukti' => [
            ['link' => 'https://evil.test/video?token=rahasia-link'],
            ['link' => 'https://evil.test/video?token=rahasia-lain'],
        ]])->assertUnprocessable()->assertJsonValidationErrors('bukti.0.link');
        $log = LogAktivitas::where('aksi', 'bukti.link.ditolak')->firstOrFail();
        $this->assertSame('evil.test', $log->rincian['domain']);
        $this->assertStringNotContainsString('rahasia', json_encode($log->toArray()));
        $this->assertSame(1, LogAktivitas::where('aksi', 'bukti.link.ditolak')->count());
    }

    public function test_isian_draf_diperiksa_ulang_saat_sumber_dicabut(): void
    {
        $isi = ['bukti' => [['nama' => 'Bukti', 'link' => 'https://drive.google.com/file/d/'.self::FILE.'/view']]];
        $this->assertTrue(KebijakanBukti::isianSah($isi));
        SumberBukti::where('host', 'drive.google.com')->update(['aktif' => false]);
        KebijakanBukti::lupakan();
        $this->assertFalse(KebijakanBukti::isianSah($isi));
        $this->assertFalse(KebijakanBukti::isianSah(['setoran' => [['link' => 'https://evil.test/bukti.pdf']]]));
        $this->assertFalse(KebijakanBukti::isianSah(['bukti' => [123]]));
        $this->assertFalse(KebijakanBukti::isianSah(['setoran' => 'bukan-daftar']));
    }
}
