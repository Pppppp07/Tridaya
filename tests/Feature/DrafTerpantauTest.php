<?php

namespace Tests\Feature;

use App\Enums\PosisiBerkas;
use App\Models\DrafTanggapan;
use App\Models\Notifikasi;
use App\Models\PermintaanDokumen;
use App\Models\RiwayatBerkas;
use App\Models\Sasaran;
use App\Models\User;
use App\Support\Pemberitahuan;
use App\Support\Rangka;
use App\Support\Terlihat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Collection;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Rapat stakeholder 8 Okt: satuan kerja yang baru punya sebagian dokumen tetap bisa mengirim
 * (dicicil), dan draf yang disimpannya terpantau Setba tanpa berpindah meja.
 */
class DrafTerpantauTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    private const LINK = 'https://contoh.test/berkas-draf/';

    private Sasaran $baris;

    private Collection $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();

        $baris = Sasaran::with('tindakan.rekomendasi.permintaanDokumen.item', 'satker')->get()
            ->first(fn ($x) => $x->pos() === PosisiBerkas::SATKER);
        $this->assertNotNull($baris, 'Data contoh perlu memuat baris yang masih di satuan kerja.');
        $this->baris = $baris;

        /* Permintaan bawaan data contoh diganti tiga dokumen supaya hitungannya pasti. */
        $rek = $this->baris->tindakan->rekomendasi;
        foreach ($rek->permintaanUntuk($this->baris->satker_id, $this->baris->tindakan_id) as $lama) {
            $lama->item->each(fn ($i) => $i->lampiran()->detach());
            $lama->item()->delete();
            $lama->delete();
        }

        $p = PermintaanDokumen::create([
            'rekomendasi_id' => $this->baris->tindakan->rekomendasi_id,
            'sasaran_id' => $this->baris->id, 'peran_peminta' => 'setba', 'tanggal' => now(),
        ]);
        foreach (['Surat usulan penghapusan', 'Daftar barang yang dihapus', 'Penetapan status penggunaan'] as $nama) {
            $p->item()->create(['nama' => $nama, 'terpenuhi' => false]);
        }
        $this->item = $p->item()->get();
    }

    private function satker(): User
    {
        return User::where('satker_id', $this->baris->satker_id)->where('peran', 'satker')->firstOrFail();
    }

    /** Dua dari tiga dokumen, seperti contoh Poltek di rapat. */
    private function isian(string $aksi): array
    {
        return [
            'aksi' => $aksi, 'uraian' => 'Penetapan status penggunaan masih menunggu Sekjen.',
            'bukti' => $this->item->take(2)->map(fn ($i) => ['nama' => $i->nama, 'link' => self::LINK.$i->id,
                'jenis' => $i->nama, 'untuk' => $i->id])->values()->all(),
        ];
    }

    /** Tiap halaman dibuka sebagai akun baru: tembolok statis bingkai dan hak lihat dilepas dulu. */
    private function halaman(string $email): string
    {
        Rangka::lupakan();
        Terlihat::lupakan();

        return $this->masuk($email)->get(route('rekomendasi.show', $this->baris->tindakan->rekomendasi_id))
            ->assertOk()->getContent();
    }

    public function test_satuan_kerja_bisa_mengirim_walau_dokumen_belum_lengkap(): void
    {
        $this->actingAs($this->satker())->post(route('tanggapan.simpan', $this->baris), $this->isian('kirim'))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(PosisiBerkas::SETBA_TINJAU, $this->baris->fresh()->pos());
        $this->assertSame([true, true, false], $this->item->map(fn ($i) => (bool) $i->fresh()->terpenuhi)->all());

        /* Progres tidak menyebut "tuntas"; dokumen yang menyusul disebut. */
        $aksi = RiwayatBerkas::where('rekomendasi_id', $this->baris->tindakan->rekomendasi_id)->latest('id')->value('aksi');
        $this->assertStringContainsString('1 dokumen menyusul', $aksi);
        $this->assertStringContainsString('2 dari 3 dokumen terpenuhi', $aksi);
        $this->assertStringNotContainsString('Kewajiban tuntas', $aksi);

        /* Setba melihat tanda kurangnya di panel Teruskan, tanpa tombol yang terkunci. */
        $isi = strip_tags($this->halaman('setba@contoh.test'));
        $this->assertStringContainsString('Dokumen yang diminta baru 2 dari 3 terlampir. Berkas tetap bisa diteruskan', $isi);

        /* Diteruskan ke UKI: nama dokumen yang belum ada ikut disebut sebagai bahan putusan. */
        $this->masuk('setba@contoh.test')->post(route('sasaran.teruskan', $this->baris->fresh()), [
            'nomor' => 'PW.02.02-Sb/9101', 'tanggal' => now()->toDateString(), 'perihal' => 'Permohonan validasi tindak lanjut',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(PosisiBerkas::UKI, $this->baris->fresh()->pos());
        $isi = strip_tags($this->halaman('uki@contoh.test'));
        $this->assertStringContainsString('Dokumen yang diminta baru 2 dari 3 terlampir. Belum: Penetapan status penggunaan.', $isi);
    }

    public function test_link_yang_belum_lengkap_tetap_menahan_kiriman(): void
    {
        $isian = $this->isian('kirim');
        $isian['bukti'][1]['link'] = '';

        $this->actingAs($this->satker())->post(route('tanggapan.simpan', $this->baris), $isian)
            ->assertSessionHasErrors('kirim');
        $this->assertSame(PosisiBerkas::SATKER, $this->baris->fresh()->pos());
    }

    public function test_draf_terpantau_setba_tanpa_berpindah_dan_tanpa_link(): void
    {
        Notification::fake();
        $satker = $this->satker();
        $notifikasi = Notifikasi::count();
        $riwayat = RiwayatBerkas::count();
        $baruSetba = Pemberitahuan::jumlahBaru($this->akun('setba@contoh.test'));

        $this->actingAs($satker)->post(route('tanggapan.simpan', $this->baris), $this->isian('draf'))
            ->assertRedirect()->assertSessionHasNoErrors();

        /* Draf tetap isian: tidak berpindah, tanpa Progres. */
        $draf = DrafTanggapan::where('sasaran_id', $this->baris->id)->firstOrFail();
        $this->assertSame($satker->id, $draf->disimpan_oleh);
        $this->assertSame(PosisiBerkas::SATKER, $this->baris->fresh()->pos());
        $this->assertSame($riwayat, RiwayatBerkas::count());

        /* Setba diberi tahu di lonceng saja: satu pemberitahuan, tanpa email. */
        $this->assertSame($notifikasi + 1, Notifikasi::count());
        $n = Notifikasi::latest('id')->firstOrFail();
        $this->assertSame(['setba'], $n->untuk_peran);
        $this->assertStringStartsWith('Draf tindak lanjut disimpan, belum dikirim — 2 dari 3 dokumen siap', $n->aksi);
        $this->assertSame([$this->baris->satker_id], $n->satker->pluck('id')->all());
        $this->assertSame(['nada' => 'biru', 'ikon' => 'Save'], Pemberitahuan::rupa($n));
        $this->assertSame($baruSetba + 1, Pemberitahuan::jumlahBaru($this->akun('setba@contoh.test')));
        Notification::assertNothingSent();

        /* Setba membaca kemajuannya, penyimpannya, dan uraiannya; link berkasnya tidak. */
        $html = $this->halaman('setba@contoh.test');
        $isi = strip_tags($html);
        $this->assertStringContainsString('Draf satuan kerja', $isi);
        $this->assertStringContainsString('Belum dikirim', $isi);
        $this->assertStringContainsString($satker->name, $isi);
        $this->assertStringContainsString('2 dari 3 dokumen siap', $isi);
        $this->assertStringContainsString('disiapkan di draf', $isi);
        $this->assertStringContainsString('Penetapan status penggunaan masih menunggu Sekjen.', $isi);
        $this->assertStringContainsString('Draf disimpan', $isi);
        $this->assertStringNotContainsString(self::LINK, $html);

        /* Satuan kerja diberi tahu bahwa drafnya terpantau; blok pantauan bukan untuknya. */
        $sendiri = strip_tags($this->halaman($satker->email));
        $this->assertStringContainsString('Setba dapat memantau kemajuannya, tetapi draf belum terkirim.', $sendiri);
        $this->assertStringNotContainsString('Draf satuan kerja', $sendiri);
    }

    public function test_draf_hanya_terbaca_setba(): void
    {
        $this->actingAs($this->satker())->post(route('tanggapan.simpan', $this->baris), $this->isian('draf'))
            ->assertSessionHasNoErrors();
        $n = Notifikasi::latest('id')->firstOrFail();
        $this->assertTrue(Pemberitahuan::boleh($n, $this->akun('setba@contoh.test')));

        foreach (['uki@contoh.test', 'inspektorat@contoh.test', 'dti@contoh.test'] as $email) {
            $isi = strip_tags($this->halaman($email));
            $this->assertStringNotContainsString('Draf satuan kerja', $isi, $email);
            $this->assertStringNotContainsString('Draf disimpan', $isi, $email);
            $this->assertStringNotContainsString('disiapkan di draf', $isi, $email);
        }
        /* Pemberitahuannya pun hanya untuk Setba. */
        foreach (['uki@contoh.test', 'inspektorat@contoh.test', 'dti@contoh.test', 'pimpinan@contoh.test', 'admin@contoh.test'] as $email) {
            $this->assertFalse(Pemberitahuan::boleh($n, $this->akun($email)), $email);
        }
        $this->assertFalse(Pemberitahuan::boleh($n, $this->satker()));
    }

    public function test_draf_yang_disimpan_ulang_hari_itu_memperbarui_pemberitahuan_yang_sama(): void
    {
        $setba = $this->akun('setba@contoh.test');
        $this->actingAs($this->satker())->post(route('tanggapan.simpan', $this->baris), $this->isian('draf'));
        $n = Notifikasi::latest('id')->firstOrFail();
        Pemberitahuan::tandai($n, $setba, true);
        $jumlah = Notifikasi::count();
        $kali = DrafTanggapan::where('sasaran_id', $this->baris->id)->value('kali');

        /* Dokumen ketiga menyusul di simpan berikutnya. */
        $isian = $this->isian('draf');
        $i = $this->item->last();
        $isian['bukti'][] = ['nama' => $i->nama, 'link' => self::LINK.$i->id, 'jenis' => $i->nama, 'untuk' => $i->id];
        $this->actingAs($this->satker())->post(route('tanggapan.simpan', $this->baris), $isian)
            ->assertSessionHasNoErrors();

        $this->assertSame($jumlah, Notifikasi::count());
        $n->refresh();
        $this->assertStringStartsWith('Draf tindak lanjut disimpan, belum dikirim — 3 dari 3 dokumen siap', $n->aksi);
        $this->assertFalse(Pemberitahuan::sudahDibaca($n->load('dibaca'), $setba), 'Kemajuan baru dibaca ulang Setba.');
        $this->assertSame($kali + 1, DrafTanggapan::where('sasaran_id', $this->baris->id)->value('kali'));
    }

    public function test_draf_hilang_dari_pantauan_begitu_dikirim(): void
    {
        $this->actingAs($this->satker())->post(route('tanggapan.simpan', $this->baris), $this->isian('draf'));
        $this->actingAs($this->satker())->post(route('tanggapan.simpan', $this->baris), $this->isian('kirim'))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, DrafTanggapan::where('sasaran_id', $this->baris->id)->count());
        $isi = strip_tags($this->halaman('setba@contoh.test'));
        $this->assertStringNotContainsString('Draf satuan kerja', $isi);
    }
}
