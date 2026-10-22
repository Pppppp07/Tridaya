<?php

namespace Tests\Feature;

use App\Models\LogAktivitas;
use App\Models\Notifikasi;
use App\Models\Rekomendasi;
use App\Support\Pemberitahuan;
use App\Support\Rangka;
use App\Support\Terlihat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/** Halaman Pemberitahuan disaring per jenis; jenis dikenali dari awal kalimatnya. */
class PemberitahuanJenisTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    private function kabar(string $aksi, array $untuk = ['setba'], int $jam = 24): Notifikasi
    {
        $r = Rekomendasi::orderBy('id')->firstOrFail();
        $s = $r->sasaran()->firstOrFail();
        $n = Notifikasi::create(['rekomendasi_id' => $r->id, 'waktu' => now()->addHours($jam), 'label_pelaku' => 'Uji',
            'aksi' => $aksi, 'untuk_peran' => $untuk, 'blok' => 'r-tindaklanjut']);
        $n->satker()->attach([$s->satker_id]);

        return $n;
    }

    private function halaman(string $email, array $q): string
    {
        Rangka::lupakan();
        Terlihat::lupakan();

        return $this->masuk($email)->get(route('pemberitahuan', $q))->assertOk()->getContent();
    }

    public function test_jenis_dikenali_dari_awal_kalimat_sama_di_php_dan_sql(): void
    {
        $contoh = [
            'Rekomendasi baru untuk Anda — Surat teguran — berkasnya di meja Anda' => 'baru',
            'Draf tindak lanjut disimpan, belum dikirim — 1 dari 2 dokumen siap' => 'draf',
            'Berkas dikirim ke Setba — 2 dari 3 dokumen terpenuhi' => 'kiriman',
            'Berkas diteruskan ke UKI dengan surat PW.02.02-Sb/1' => 'terus',
            'Validasi UKI selesai — memadai, surat PW 01' => 'putusan',
            'Validasi UKI: belum memadai — berkas di Setba untuk pemberkasan ulang.' => 'putusan',
            'Verifikasi selesai — memadai' => 'putusan',
            'Hasil verifikasi Inspektorat dicatat — belum memadai' => 'putusan',
            'Dikirim ulang ke Balai Wil. I Medan untuk pemberkasan ulang — ditolak BPK' => 'ulang',
            'Unggahan ke SIPTL dicatat — Balai Wil. I Medan, diunggah 1 Okt 2026' => 'siptl',
            'Status SIPTL dicatat — Balai Wil. I Medan, Sudah sesuai' => 'siptl',
            'Kalimat lama dari data contoh' => 'lain',
        ];
        foreach ($contoh as $aksi => $jenis) {
            $n = $this->kabar($aksi);
            $this->assertSame($jenis, Pemberitahuan::jenis($n), $aksi);
            foreach (array_keys(Pemberitahuan::JENIS) as $j) {
                $this->assertSame($j === $jenis, Pemberitahuan::saringJenis(Notifikasi::whereKey($n->id), $j)->exists(), "{$aksi} ~ {$j}");
            }
        }

        $log = LogAktivitas::create(['aksi' => 'master.unit', 'kelompok' => 'master', 'ringkasan' => 'Mengubah satuan kerja', 'waktu' => now()]);
        $sistem = Notifikasi::create(['log_aktivitas_id' => $log->id, 'waktu' => now(), 'label_pelaku' => 'Admin',
            'aksi' => 'Mengubah satuan kerja', 'untuk_peran' => ['admin']]);
        $this->assertSame('sistem', Pemberitahuan::jenis($sistem));
        $this->assertTrue(Pemberitahuan::saringJenis(Notifikasi::whereKey($sistem->id), 'sistem')->exists());
        $this->assertFalse(Pemberitahuan::saringJenis(Notifikasi::whereKey($sistem->id), 'lain')->exists());
    }

    public function test_halaman_menyaring_per_jenis_dan_angkanya_mengikuti(): void
    {
        $this->kabar('Berkas dikirim ke Setba — uji saring kiriman');
        $this->kabar('Validasi UKI selesai — uji saring putusan');
        $setba = $this->akun('setba@contoh.test');
        $hitung = Pemberitahuan::hitungJenis($setba);

        $html = $this->halaman('setba@contoh.test', ['jenis' => 'kiriman', 'tab' => 'semua']);
        $isi = strip_tags($html);
        $this->assertStringContainsString('uji saring kiriman', $isi);
        $this->assertStringNotContainsString('uji saring putusan', $isi);
        $this->assertMatchesRegularExpression('/data-jenis-pilih="kiriman"[^>]*aria-current="true"/s', $html);
        /* Tab "Semua" menghitung jenis yang dipilih; ringkasan atas tetap seluruh jenis. */
        $this->assertMatchesRegularExpression('/data-tab-pemberitahuan="semua">Semua <span class="n">'.$hitung['semua']['kiriman'].'</', $html);
        $this->assertStringContainsString('data-jml-saring', $html);
        $this->assertStringContainsString('Tandai yang tampil dibaca', $isi);

        /* Jenis yang tidak dikenal diabaikan. */
        $semua = strip_tags($this->halaman('setba@contoh.test', ['jenis' => 'tidak-ada', 'tab' => 'semua']));
        $this->assertStringContainsString('uji saring kiriman', $semua);
        $this->assertStringContainsString('uji saring putusan', $semua);
    }

    public function test_tandai_yang_tampil_hanya_menandai_jenis_itu(): void
    {
        $kiriman = $this->kabar('Berkas dikirim ke Setba — uji tandai');
        $putusan = $this->kabar('Validasi UKI selesai — uji tandai');
        $setba = $this->akun('setba@contoh.test');

        $this->actingAs($setba)->post(route('pemberitahuan.semua', ['jenis' => 'kiriman']))->assertRedirect();
        $this->assertTrue(Pemberitahuan::sudahDibaca($kiriman->fresh('dibaca'), $setba));
        $this->assertFalse(Pemberitahuan::sudahDibaca($putusan->fresh('dibaca'), $setba));
        $this->assertSame(0, Pemberitahuan::hitungJenis($setba)['belum']['kiriman'] ?? 0);
    }

    public function test_hanya_jenis_yang_ada_yang_ditawarkan(): void
    {
        $this->kabar('Berkas diteruskan ke UKI dengan surat uji', ['uki']);
        $uki = $this->akun('uki@contoh.test');
        $hitung = Pemberitahuan::hitungJenis($uki);
        $this->assertArrayNotHasKey('draf', $hitung['semua']);

        $html = $this->halaman('uki@contoh.test', ['tab' => 'semua']);
        $this->assertStringContainsString('data-jenis-pilih="terus"', $html);
        $this->assertStringNotContainsString('data-jenis-pilih="draf"', $html);
        $this->assertStringNotContainsString('data-jenis-pilih="sistem"', $html);
    }
}
