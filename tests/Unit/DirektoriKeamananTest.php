<?php

namespace Tests\Unit;

use App\Support\Direktori\DirektoriHttp;
use App\Support\Direktori\GagalDirektori;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DirektoriKeamananTest extends TestCase
{
    private function konfig(array $opsi = []): array
    {
        return array_merge(['url' => 'https://ehrm.contoh.test/api', 'token' => 'layanan-a',
            'jalur' => ['cari' => '/pegawai?q={q}'], 'wadah' => 'data', 'simpan_menit' => 30, 'batas_detik' => 2], $opsi);
    }

    public function test_http_tidak_mengirim_token_layanan(): void
    {
        Http::fake([]);
        try {
            (new DirektoriHttp($this->konfig(['url' => 'http://ehrm.contoh.test/api'])))->cari('sri', 10);
            $this->fail('HTTP harus ditolak sebelum request.');
        } catch (GagalDirektori $e) {
            $this->assertStringContainsString('HTTPS', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_format_json_rusak_dibedakan_dari_pegawai_tidak_ditemukan(): void
    {
        Http::fake(['ehrm.contoh.test/*' => Http::response('bukan-json', 200)]);
        $this->expectException(GagalDirektori::class);
        (new DirektoriHttp($this->konfig()))->cari('sri', 10);
    }

    public function test_redirect_api_ditolak(): void
    {
        Http::fake(['ehrm.contoh.test/*' => Http::response('', 302, ['Location' => 'https://tujuan-lain.test/api'])]);
        $this->expectException(GagalDirektori::class);
        (new DirektoriHttp($this->konfig()))->cari('sri', 10);
    }

    public function test_cache_tidak_dipakai_silang_antara_kredensial_layanan(): void
    {
        Http::fake(['ehrm.contoh.test/*' => fn ($r) => Http::response(['data' => [[
            'nip' => '198001012005011001', 'nama' => $r->hasHeader('Authorization', 'Bearer layanan-a') ? 'Layanan A' : 'Layanan B',
        ]]])]);
        $a = (new DirektoriHttp($this->konfig()))->cari('sri', 10)->first();
        $b = (new DirektoriHttp($this->konfig(['token' => 'layanan-b'])))->cari('sri', 10)->first();
        $this->assertSame('Layanan A', $a['nama']);
        $this->assertSame('Layanan B', $b['nama']);
        Http::assertSentCount(2);
    }
}
