<?php

namespace Tests\Feature;

use App\Models\Notifikasi;
use App\Models\Rekomendasi;
use App\Support\Pemberitahuan;
use App\Support\Rangka;
use App\Support\Terlihat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Daftar Rekomendasi: yang baru diperbarui di atas, titik merah untuk yang belum dilihat, dan
 * barisnya membuka bagian yang berubah. Bisa dimatikan di Profil → Pemberitahuan.
 */
class PembaruanTerbaruTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    /** Pemberitahuan tentang satu satuan kerja di rekomendasi ini, sekian jam sesudah jam uji. */
    private function kabar(Rekomendasi $r, int $jam, string $aksi = 'Berkas dikirim ke Setba — 1 dari 2 dokumen terpenuhi',
        array $untuk = ['setba']): Notifikasi
    {
        $s = $r->sasaran()->with('satker')->firstOrFail();
        $n = Notifikasi::create(['rekomendasi_id' => $r->id, 'waktu' => now()->addHours($jam),
            'label_pelaku' => $s->satker->namaPendek(), 'aksi' => $aksi, 'untuk_peran' => $untuk, 'blok' => 'r-tindaklanjut']);
        $n->satker()->attach([$s->satker_id]);

        return $n;
    }

    private function daftar(string $email, array $q = ['keadaan' => 'semua']): string
    {
        Rangka::lupakan();
        Terlihat::lupakan();

        return $this->masuk($email)->get(route('rekomendasi.index', $q))->assertOk()->getContent();
    }

    public function test_bawaannya_pembaruan_terbaru_di_atas(): void
    {
        [$a, $b] = Rekomendasi::orderBy('id')->take(2)->get()->all();
        $this->kabar($a, 24);
        $this->kabar($b, 48);

        $html = $this->daftar('setba@contoh.test');
        $posA = strpos($html, 'data-rek="'.$a->id.'"');
        $posB = strpos($html, 'data-rek="'.$b->id.'"');
        $this->assertNotFalse($posA);
        $this->assertLessThan($posA, $posB, 'Pembaruan paling baru harus di atas.');
        $this->assertStringContainsString('Pembaruan terbaru di atas', strip_tags($html));
        $this->assertStringContainsString('name="urut" value="terbaru"', $html);
    }

    public function test_titik_merah_sampai_dibuka_lalu_menuju_bagian_yang_berubah(): void
    {
        $r = Rekomendasi::orderBy('id')->firstOrFail();
        $lama = $this->kabar($r, 24, 'Validasi UKI selesai — belum memadai');
        $baru = $this->kabar($r, 30);

        $html = $this->daftar('setba@contoh.test');
        $this->assertStringContainsString(route('rekomendasi.terbaru', $r), $html);
        $isi = strip_tags($html);
        $this->assertStringContainsString('Baru · Berkas dikirim ke Setba', $isi);
        $this->assertMatchesRegularExpression('/\d+ lainnya/', $isi);

        /* Membuka: semua yang belum dilihat ditandai dibaca, dan rinciannya menuju bagian terbaru. */
        $setba = $this->akun('setba@contoh.test');
        $tujuan = route('rekomendasi.show', Pemberitahuan::tujuan($baru->fresh('satker'), $setba));
        $this->actingAs($setba)->get(route('rekomendasi.terbaru', $r))->assertRedirect($tujuan);
        $this->assertStringContainsString('sorot=r-tindaklanjut', $tujuan);
        foreach ([$lama, $baru] as $n) {
            $this->assertTrue(Pemberitahuan::sudahDibaca($n->fresh('dibaca'), $setba));
        }
        $this->assertStringNotContainsString(route('rekomendasi.terbaru', $r), $this->daftar('setba@contoh.test'));
    }

    public function test_satuan_kerja_melihat_titik_untuk_pembaruannya_sendiri(): void
    {
        $medan = $this->akun('medan');
        $r = Rekomendasi::all()->first(fn ($x) => $x->dituju($medan->satker_id));
        $this->assertNotNull($r);
        $s = $r->sasaran()->where('satker_id', $medan->satker_id)->firstOrFail();
        $n = Notifikasi::create(['rekomendasi_id' => $r->id, 'waktu' => now()->addDay(), 'label_pelaku' => 'Setba',
            'aksi' => 'Dikirim ulang ke satuan kerja untuk pemberkasan ulang', 'untuk_peran' => ['satker'], 'blok' => 'r-tindaklanjut']);
        $n->satker()->attach([$s->satker_id]);

        $html = $this->daftar($medan->email);
        $this->assertStringContainsString(route('rekomendasi.terbaru', $r), $html);
        $this->assertStringContainsString('Baru · Dikirim ulang ke satuan kerja untuk pemberkasan ulang ·', strip_tags($html));
    }

    public function test_bisa_dimatikan_di_pengaturan(): void
    {
        $r = Rekomendasi::orderBy('id')->firstOrFail();
        $this->kabar($r, 24);
        $setba = $this->akun('setba@contoh.test');

        $akun = $this->actingAs($setba)->get(route('akun', ['tab' => 'pemberitahuan']))->assertOk()->getContent();
        $this->assertStringContainsString('Sorot pembaruan di daftar Rekomendasi', $akun);
        $this->assertMatchesRegularExpression('/name="pembaruan" value="1"\s+checked/', $akun);

        $this->actingAs($setba)->post(route('akun.setelan'), ['bagian' => 'pemberitahuan', 'popup' => '1'])->assertRedirect();
        $this->assertFalse($setba->fresh()->setelan('pembaruan'));
        $this->assertDatabaseHas('log_aktivitas', ['aksi' => 'akun.setelan', 'user_id' => $setba->id]);

        $html = $this->daftar('setba@contoh.test');
        $this->assertStringNotContainsString(route('rekomendasi.terbaru', $r), $html);
        $this->assertStringContainsString('name="urut" value="mendesak"', $html);
        $this->assertStringContainsString('Yang mendesak di atas', strip_tags($html));
    }

    public function test_rekomendasi_satuan_kerja_lain_tidak_bisa_dibuka_lewat_terbaru(): void
    {
        $medan = $this->akun('medan');
        $r = Rekomendasi::all()->first(fn ($x) => ! $x->dituju($medan->satker_id));
        $this->assertNotNull($r);
        $this->actingAs($medan)->get(route('rekomendasi.terbaru', $r))->assertForbidden();
    }
}
