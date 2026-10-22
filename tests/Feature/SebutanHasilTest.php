<?php

namespace Tests\Feature;

use App\Enums\HasilTelaah;
use App\Enums\PosisiBerkas;
use App\Enums\SumberLaporan;
use App\Models\Sasaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/** Sebutan hasil UKI/Itjen ikut jenis laporan: LHA Sesuai (S), LHP Memadai (M); SS milik BPK. */
class SebutanHasilTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    private function baris(SumberLaporan $jenis): Sasaran
    {
        $x = Sasaran::with('tindakan.rekomendasi.temuan.laporan')->get()
            ->first(fn ($s) => $s->tindakan->rekomendasi->jenis() === $jenis && $s->hasil_uki === HasilTelaah::M);
        $this->assertNotNull($x, "Data contoh perlu baris {$jenis->value} yang sudah divalidasi UKI.");

        return $x;
    }

    public function test_keping_tabel_lha_berbunyi_s_bukan_ss(): void
    {
        $isi = $this->masuk('setba@contoh.test')
            ->get(route('rekomendasi.show', $this->baris(SumberLaporan::LHA)->tindakan->rekomendasi))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/title="Sesuai[^"]*" aria-label="Sesuai[^"]*">S<\/span>/u', $isi);
        $this->assertDoesNotMatchRegularExpression('/aria-label="Sesuai[^"]*">SS<\/span>/u', $isi);
    }

    public function test_keping_tabel_lhp_tetap_m(): void
    {
        $isi = $this->masuk('setba@contoh.test')
            ->get(route('rekomendasi.show', $this->baris(SumberLaporan::LHP)->tindakan->rekomendasi))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/title="Memadai[^"]*" aria-label="Memadai[^"]*">M<\/span>/u', $isi);
    }

    /** Keterangan Status verifikasi: rangkuman verifikasi Inspektorat, dengan sebutan jenis laporannya. */
    public function test_keterangan_status_verifikasi_menyebut_keputusan_itjen(): void
    {
        $this->masuk('setba@contoh.test');
        $this->get(route('rekomendasi.show', $this->baris(SumberLaporan::LHA)->tindakan->rekomendasi))->assertOk()
            ->assertSee('Rangkuman hasil verifikasi Inspektorat')
            ->assertSee('Sesuai jika seluruh penugasan sudah dinyatakan sesuai.')
            ->assertDontSee('dinyatakan memadai');
        $this->get(route('rekomendasi.show', $this->baris(SumberLaporan::LHP)->tindakan->rekomendasi))->assertOk()
            ->assertSee('Memadai jika seluruh penugasan sudah dinyatakan memadai.')
            ->assertSee('rangkumannya Belum memadai.');
    }

    public function test_pesan_putusan_mengikuti_jenis_laporan(): void
    {
        foreach ([SumberLaporan::LHA->value => ['sesuai', 'belum sesuai'], SumberLaporan::LHP->value => ['memadai', 'belum memadai']] as $jenis => [$kataM, $kataBM]) {
            $x = $this->baris(SumberLaporan::from($jenis));
            $x->update(['posisi' => PosisiBerkas::UKI, 'hasil' => null]);

            $this->masuk('uki@contoh.test')->post(route('sasaran.putus', $x), ['hasil' => 'M'])
                ->assertSessionHasErrors(['nomor' => "Nomor dan tanggal surat harus terisi — putusan {$kataM} berdiri di atas suratnya."]);

            $bukti = $x->lampiran()->value('id') ?? $x->lampiran()->create([
                'tindakan_id' => $x->tindakan_id, 'nama_asli' => 'Bukti uji', 'label_jenis' => 'Bukti tindak lanjut',
                'link' => 'https://contoh.test/bukti-uji', 'label_oleh' => 'Uji', 'diunggah_pada' => now()->toDateString(),
            ])->id;
            $this->post(route('sasaran.putus', $x), ['hasil' => 'M', 'nomor' => '1/UJI', 'tgl_surat' => now()->toDateString(),
                'bukti_tolak' => [$bukti]])
                ->assertSessionHasErrors(['bukti_tolak' => "Pilih {$kataBM} jika ada bukti yang perlu diganti."]);
        }
    }
}
