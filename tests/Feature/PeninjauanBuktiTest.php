<?php

namespace Tests\Feature;

use App\Enums\PosisiBerkas;
use App\Models\DrafLaporan;
use App\Models\ItemPermintaan;
use App\Models\Lampiran;
use App\Models\PermintaanDokumen;
use App\Models\Sasaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\PakaiDataContoh;
use Tests\TestCase;

class PeninjauanBuktiTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    private Sasaran $baris;

    private ItemPermintaan $item;

    private const LINK = 'https://drive.google.com/file/d/1AbcDEFghiJKLmnoPQRstUVwxyZ/view';

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        $this->baris = Sasaran::with('tindakan.rekomendasi', 'satker')->firstOrFail();
        $this->baris->update(['posisi' => PosisiBerkas::UKI]);
        $permintaan = PermintaanDokumen::create([
            'rekomendasi_id' => $this->baris->tindakan->rekomendasi_id,
            'sasaran_id' => $this->baris->id, 'peran_peminta' => 'setba', 'tanggal' => now(),
        ]);
        $this->item = $permintaan->item()->create(['nama' => 'Berita acara', 'terpenuhi' => true, 'dipenuhi_pada' => now()]);
    }

    private function bukti(?Sasaran $s = null): Lampiran
    {
        $s ??= $this->baris;
        $b = Lampiran::create(['sasaran_id' => $s->id, 'rekomendasi_id' => $s->tindakan->rekomendasi_id,
            'tindakan_id' => $s->tindakan_id, 'nama_asli' => 'Berita acara', 'link' => self::LINK]);
        if ($s->id === $this->baris->id) {
            $this->item->lampiran()->attach($b);
        }

        return $b;
    }

    public function test_seluruh_bukti_satu_butir_ditolak_arsip_tetap_ada_dan_butir_perlu_pengganti(): void
    {
        $a = $this->bukti();
        $b = $this->bukti();
        $uki = $this->akun('uki@contoh.test');
        $this->actingAs($uki)->post(route('sasaran.putus', $this->baris), [
            'hasil' => 'BM', 'catatan' => 'Isi file tidak sesuai dengan berita acara yang diminta.',
            'bukti_tolak' => [$a->id, $b->id],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($this->item->fresh()->terpenuhi);
        $this->assertNull($this->item->fresh()->dipenuhi_pada);
        $this->assertSame(PosisiBerkas::SETBA_KEMBALI, $this->baris->fresh()->pos());
        foreach ([$a, $b] as $arsip) {
            $this->assertSame(self::LINK, $arsip->fresh()->link);
            $this->assertSame($uki->id, $arsip->fresh()->ditolak_oleh);
            $this->get(route('berkas.buka', $arsip))->assertStatus(422);
        }
        $this->assertDatabaseHas('log_aktivitas', ['aksi' => 'bukti.ditolak', 'user_id' => $uki->id]);

        /* Pengiriman pengganti memenuhi butir yang sama tanpa menghapus arsip lama. */
        $this->baris->update(['posisi' => PosisiBerkas::SATKER]);
        $satker = User::where('satker_id', $this->baris->satker_id)->where('peran', 'satker')->firstOrFail();
        $this->actingAs($satker)->post(route('tanggapan.simpan', $this->baris), [
            'aksi' => 'kirim', 'uraian' => 'Berita acara diperbaiki dan dilampirkan kembali.',
            'bukti' => [['nama' => 'Berita acara baru', 'link' => self::LINK, 'untuk' => $this->item->id]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($this->item->fresh()->terpenuhi);
        $this->assertSame(3, $this->item->lampiran()->count());
        $this->assertSame(2, $this->item->lampiran()->whereNotNull('ditolak_pada')->count());
    }

    public function test_bukti_pengganti_yang_tidak_ditolak_tetap_memenuhi_butir(): void
    {
        $a = $this->bukti();
        $b = $this->bukti();
        $this->masuk('uki@contoh.test')->post(route('sasaran.putus', $this->baris), [
            'hasil' => 'BM', 'catatan' => 'Satu lampiran tidak sesuai, lampiran lain masih sesuai.', 'bukti_tolak' => [$a->id],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($this->item->fresh()->terpenuhi);
        $this->assertNull($b->fresh()->ditolak_pada);
    }

    public function test_pemeriksa_tidak_bisa_menolak_bukti_sasaran_lain_atau_menolak_sambil_memadai(): void
    {
        $lain = Sasaran::with('tindakan')->where('id', '!=', $this->baris->id)->firstOrFail();
        $b = $this->bukti($lain);
        $this->masuk('uki@contoh.test')->post(route('sasaran.putus', $this->baris), [
            'hasil' => 'BM', 'catatan' => 'Bukti tidak sesuai yang diminta.', 'bukti_tolak' => [$b->id],
        ])->assertSessionHasErrors('bukti_tolak.0');
        $this->assertNull($b->fresh()->ditolak_pada);
        $this->assertSame(PosisiBerkas::UKI, $this->baris->fresh()->pos());
        $b = $this->bukti();
        $this->post(route('sasaran.putus', $this->baris), ['hasil' => 'M', 'nomor' => '123/UKI',
            'tgl_surat' => now()->toDateString(), 'bukti_tolak' => [$b->id]])->assertSessionHasErrors('bukti_tolak');
        $this->assertNull($b->fresh()->ditolak_pada);
    }

    public function test_link_laporan_ditolak_tanpa_menghilangkan_ketikan_di_form(): void
    {
        $this->masuk('setba@contoh.test')->from(route('laporan.baru'))->post(route('laporan.baru.simpan'), [
            'aksi' => 'simpan', 'surat' => ['nomor' => '77/LHP'],
            'berkas' => ['judul' => 'Judul laporan uji', 'link' => 'https://evil.test/bukti.pdf'],
        ])->assertRedirect(route('laporan.baru'))->assertSessionHasErrors('berkas.link')
            ->assertSessionHasInput('berkas.judul', 'Judul laporan uji');
        $layar = $this->get(route('laporan.baru'))->assertOk();
        $this->assertSame('Judul laporan uji', $layar['d']['berkas']['judul']);
        $this->assertSame('77/LHP', $layar['d']['surat']['nomor']);
        $this->assertSame('https://evil.test/bukti.pdf', $layar['d']['berkas']['link']);
        $this->assertSame(0, DrafLaporan::where('user_id', auth()->id())->count());
    }

    public function test_link_laporan_berbentuk_array_tidak_merusak_form_sesudah_ditolak(): void
    {
        $this->masuk('setba@contoh.test')->from(route('laporan.baru'))->post(route('laporan.baru.simpan'), [
            'aksi' => 'simpan', 'berkas' => ['judul' => 'Judul tetap tersedia', 'link' => ['https://evil.test/bukti.pdf']],
        ])->assertRedirect(route('laporan.baru'))->assertSessionHasErrors('berkas.link');
        $layar = $this->get(route('laporan.baru'))->assertOk();
        $this->assertSame('Judul tetap tersedia', $layar['d']['berkas']['judul']);
        $this->assertSame('', $layar['d']['berkas']['link']);
        $this->post(route('laporan.baru.simpan'), ['aksi' => 'simpan',
            'berkas' => ['judul' => 'Arsip laporan', 'link' => self::LINK]])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('laporan.baru.simpan'), ['aksi' => 'simpan', 'berkas' => ['link' => '']])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('', DrafLaporan::where('user_id', auth()->id())->firstOrFail()->isian['berkas']['link']);
        $this->baris->update(['posisi' => PosisiBerkas::SATKER]);
        $satker = User::where('satker_id', $this->baris->satker_id)->where('peran', 'satker')->firstOrFail();
        foreach (['bukti', 'setoran'] as $field) {
            $this->actingAs($satker)->post(route('tanggapan.simpan', $this->baris), [
                'aksi' => 'draf', 'uraian' => 'Uraian tanggapan', $field => [123],
            ])->assertRedirect()->assertSessionHasErrors($field.'.0');
        }
    }
}
