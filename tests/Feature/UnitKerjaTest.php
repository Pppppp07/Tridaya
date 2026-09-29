<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Models\Rekomendasi;
use App\Models\Satker;
use App\Models\User;
use App\Notifications\PemberitahuanEmail;
use App\Support\DirektoriIrm;
use App\Support\Pemberitahuan;
use App\Support\Terlihat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Data master unit kerja dan penanggung jawabnya (18 Sep).
 *
 * Kata Bang Kamal: "Master unit kerja nggak ada nih"; penanggung jawab "ketik
 * dari IRM akunnya … data sama emailnya otomatis … jangan tertukar
 * atributnya"; "Cukup satu orang aja yang nginput"; dan "Notif email lah!"
 */
class UnitKerjaTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        $this->masuk('setba@contoh.test');
    }

    private function unit(string $kode): Satker
    {
        return Satker::where('kode', $kode)->firstOrFail();
    }

    /** Formulir laporan baru di langkah temuan — tempat satuan kerja dipilih. */
    private function formTemuan(): string
    {
        /* Formulirnya satu halaman: pemilih satuan kerja terperiksa sudah
           tampil begitu suratnya terisi. */
        $this->post(route('laporan.baru.simpan'), [
            'aksi' => 'simpan',
            'surat' => ['sumber' => 'LHP', 'nomor' => '88/LHP/XVIII/09/2026',
                'tgl_surat' => '2026-08-01', 'tgl_terima' => '2026-08-05'],
            'berkas' => ['judul' => 'Laporan 88', 'link' => 'https://contoh.test/88.pdf'],
        ])->assertRedirect(route('laporan.baru'));

        return $this->get(route('laporan.baru'))->assertOk()->getContent();
    }

    public function test_tiap_unit_kerja_contoh_punya_satu_penanggung_jawab_dari_irm(): void
    {
        foreach (Satker::all() as $s) {
            $aktif = User::where('peran', PeranPengguna::SATKER->value)->where('satker_id', $s->id)
                ->where('aktif', true)->get();
            $this->assertCount(1, $aktif, $s->kode.' harus punya tepat satu akun aktif');

            $irm = DirektoriIrm::nip($aktif->first()->nip);
            $this->assertNotNull($irm, $s->kode.': penanggung jawabnya harus ada di IRM');
            $this->assertSame([$irm['nama'], $irm['email'], $irm['unit']],
                [$aktif->first()->name, $aktif->first()->email, $s->kode]);
        }

        $isi = $this->get(route('master'))->assertOk()->getContent();
        $bandung = $this->unit('BALAI-4-BANDUNG')->penanggungJawab;
        $this->assertStringContainsString($bandung->name, $isi);
        $this->assertStringContainsString('NIP '.DirektoriIrm::nipTampil($bandung->nip), $isi);
        $this->assertStringContainsString('Semua unit aktif punya penanggung jawab', $isi);
    }

    public function test_unit_kerja_baru_mulai_tanpa_penanggung_jawab_dan_ditawarkan_di_formulir(): void
    {
        $this->post(route('master.unit.tambah'), [
            'nama' => 'Balai Pengembangan Kompetensi Pekerjaan Umum dan Perumahan Rakyat Wilayah X Kupang',
            'pendek' => 'Balai Wil. X Kupang',
        ])->assertRedirect();

        $kupang = Satker::where('nama_pendek', 'Balai Wil. X Kupang')->firstOrFail();
        $this->assertTrue($kupang->aktif);
        $this->assertSame('balai', $kupang->jenis);
        $this->assertNull($kupang->penanggungJawab);
        $this->assertStringContainsString('1 belum punya penanggung jawab',
            $this->get(route('master'))->getContent());

        /* Nama kembar ditolak. */
        $this->post(route('master.unit.tambah'), ['nama' => 'Apa saja', 'pendek' => 'balai wil. iv bandung'])
            ->assertSessionHas('gagal');
        $this->assertSame(0, Satker::where('nama', 'Apa saja')->count());

        /* Belum dipakai berkas: namanya masih boleh dibetulkan. */
        $this->post(route('master.unit.simpan', $kupang), [
            'nama' => $kupang->nama, 'pendek' => 'Balai Wil. X Kupang (NTT)',
        ])->assertRedirect();
        $this->assertSame('Balai Wil. X Kupang (NTT)', $kupang->fresh()->nama_pendek);

        $this->assertStringContainsString('data-pendek="Balai Wil. X Kupang (NTT)"', $this->formTemuan());
    }

    public function test_nama_unit_kerja_yang_sudah_dipakai_terkunci(): void
    {
        $medan = $this->unit('BALAI-1-MEDAN');
        $this->assertTrue($medan->dipakai());

        $this->post(route('master.unit.simpan', $medan), ['nama' => 'Nama baru', 'pendek' => 'Baru'])
            ->assertSessionHas('gagal');
        $this->assertSame('Balai Wil. I Medan', $medan->fresh()->nama_pendek);
    }

    public function test_unit_kerja_nonaktif_tidak_ditawarkan_tapi_pekerjaannya_tetap(): void
    {
        $medan = $this->unit('BALAI-1-MEDAN');
        $jalan = Rekomendasi::all()->filter(fn ($r) => $r->dituju($medan->id))->count();
        $keping = 'data-pendek="Balai Wil. I Medan"';
        $this->assertSame(1, substr_count($this->formTemuan(), $keping));

        $this->post(route('master.unit.saklar', $medan))->assertRedirect();
        $this->assertFalse($medan->fresh()->aktif);

        $this->assertSame(0, substr_count($this->get(route('laporan.baru'))->getContent(), $keping));
        $this->assertSame($jalan, Rekomendasi::all()->filter(fn ($r) => $r->dituju($medan->id))->count());

        /* Penanggung jawabnya tetap bisa masuk dan melihat pekerjaannya. */
        $this->masuk('medan')->get(route('rekomendasi.index'))->assertOk();
    }

    public function test_mengganti_penanggung_jawab_mengambil_data_irm_dan_menutup_akun_lama(): void
    {
        config(['simtlhp.password_demo' => 'rahasia123']);
        $bandung = $this->unit('BALAI-4-BANDUNG');
        $lama = $bandung->penanggungJawab;
        $rina = DirektoriIrm::semua()->firstWhere('nama', 'Rina Wulandari');

        /* Yang dikirim cuma NIP — nama dan email palsu di permintaan diabaikan. */
        $this->post(route('master.unit.pj', $bandung), [
            'nip' => DirektoriIrm::nipTampil($rina['nip']),
            'name' => 'Orang Lain', 'email' => 'palsu@contoh.test',
        ])->assertRedirect();

        $baru = $bandung->fresh()->penanggungJawab;
        $this->assertSame([$rina['nama'], $rina['nip'], $rina['email'], $rina['jabatan']],
            [$baru->name, $baru->nip, $baru->email, $baru->jabatan]);
        $this->assertSame(PeranPengguna::SATKER, $baru->peran);
        $this->assertFalse($lama->fresh()->aktif);
        $this->assertSame(0, User::where('email', 'palsu@contoh.test')->count());

        /* Yang lama tidak bisa masuk lagi, dan sesi yang masih terbuka ditutup. */
        auth()->logout();
        $this->post(route('masuk'), ['email' => $lama->email, 'password' => 'rahasia123'])
            ->assertSessionHasErrors('email');
        $this->actingAs($lama->fresh())->get(route('rekomendasi.index'))->assertRedirect(route('masuk'));
        $this->assertGuest();

        /* Yang baru bisa masuk — dengan password demo. */
        $this->post(route('masuk'), ['email' => $rina['email'], 'password' => 'rahasia123'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($baru);
    }

    public function test_satu_orang_tidak_memegang_dua_unit_kerja(): void
    {
        $jakarta = $this->unit('BALAI-3-JAKARTA')->penanggungJawab;

        $this->post(route('master.unit.pj', $this->unit('BALAI-4-BANDUNG')), ['nip' => $jakarta->nip])
            ->assertRedirect()->assertSessionHas('gagal');
        $this->assertSame($jakarta->satker_id, $jakarta->fresh()->satker_id);
        $this->assertTrue($jakarta->fresh()->aktif);
    }

    public function test_pencarian_irm_menampilkan_nip_untuk_nama_kembar(): void
    {
        $bandung = $this->unit('BALAI-4-BANDUNG');
        $isi = $this->get(route('master.unit.irm', [$bandung, 'q' => 'budi']))->assertOk()->getContent();

        $budi = DirektoriIrm::semua()->where('nama', 'Budi Setiawan')->values();
        $this->assertCount(2, $budi);
        foreach ($budi as $b) {
            $this->assertStringContainsString(DirektoriIrm::nipTampil($b['nip']), $isi);
        }
        /* Yang sudah memegang Jakarta tidak bisa dipilih. */
        $this->assertStringContainsString('Sudah memegang Balai Wil. III Jakarta', $isi);

        /* Tanpa kata pencari: pegawai unit itu sendiri. Lewat NIP berspasi juga ketemu. */
        $this->get(route('master', ['pj' => $bandung->id]))->assertOk()
            ->assertSee('Pegawai Balai Wil. IV Bandung di IRM')
            ->assertSee('Rina Wulandari');
        $rina = DirektoriIrm::semua()->firstWhere('nama', 'Rina Wulandari');
        $this->get(route('master.unit.irm', [$bandung, 'q' => substr(DirektoriIrm::nipTampil($rina['nip']), 0, 15)]))
            ->assertSee('Rina Wulandari');

        $this->masuk('uki@contoh.test')->get(route('master.unit.irm', [$bandung, 'q' => 'budi']))->assertForbidden();
    }

    public function test_pemberitahuan_untuk_satuan_kerja_dikirim_ke_email_penanggung_jawabnya(): void
    {
        Notification::fake();
        $r = Rekomendasi::all()->first(fn ($x) => $x->semuaBaris()->pluck('satker_id')->unique()->count() > 1);
        [$satu, $dua] = $r->semuaBaris()->pluck('satker_id')->unique()->values()->all();

        $pemberitahuan = Pemberitahuan::tulis($r, 'Dikirim ulang untuk pemberkasan ulang', [PeranPengguna::SATKER], 'r-riwayat', [$satu]);
        /* Email dikirim sesudah jawaban sampai ke browser (27 Sep) — di sini
           akhir permintaannya ditiru. */
        Notification::assertNothingSent();
        $this->app->terminate();

        $pjSatu = Satker::find($satu)->penanggungJawab;
        $pjDua = Satker::find($dua)->penanggungJawab;
        Notification::assertSentTo($pjSatu, PemberitahuanEmail::class, fn ($n) => $n->pemberitahuan->is($pemberitahuan));
        Notification::assertNotSentTo($pjDua, PemberitahuanEmail::class);
        Notification::assertNotSentTo($this->akun('setba@contoh.test'), PemberitahuanEmail::class);

        $email = (new PemberitahuanEmail($pemberitahuan))->toMail($pjSatu);
        $this->assertStringContainsString($r->kode, $email->subject);
        $this->assertSame(route('pemberitahuan.buka', $pemberitahuan), $email->actionUrl);
    }

    /**
     * Pemilih satuan kerja tiap tindak lanjut menyebut siapa yang akan
     * diberi tahu — dan unit kerja yang belum punya penanggung jawab disebut
     * terang-terangan.
     */
    public function test_formulir_menyebut_siapa_yang_diberi_tahu(): void
    {
        $this->post(route('master.unit.tambah'), [
            'nama' => 'Balai Pengembangan Kompetensi Pekerjaan Umum dan Perumahan Rakyat Wilayah X Kupang',
            'pendek' => 'Balai Wil. X Kupang',
        ]);
        $kupang = Satker::where('nama_pendek', 'Balai Wil. X Kupang')->firstOrFail();
        $medan = $this->unit('BALAI-1-MEDAN');
        $this->formTemuan();

        $d = \App\Models\DrafLaporan::where('user_id', auth()->id())->firstOrFail()->isian;
        $t = $d['temuan'][0];
        $r = $t['rekom'][0];
        $this->post(route('laporan.baru.simpan'), ['aksi' => 'simpan', 'tem' => [$t['id'] => [
            'satker' => [$medan->id, $kupang->id],
            'rekom' => [$r['id'] => ['tindakan' => [$r['tindakan'][0]['id'] => [
                'bentuk' => 'Surat teguran', 'satker' => [$medan->id, $kupang->id],
            ]]]],
        ]]])->assertRedirect(route('laporan.baru'));

        $teks = preg_replace('/\s+/u', ' ', strip_tags($this->get(route('laporan.baru'))->getContent()));
        $this->assertStringContainsString('Pemberitahuan dikirim lewat aplikasi dan email ke: '.$medan->penanggungJawab->name
            .' (Balai Wil. I Medan).', $teks);
        $this->assertStringContainsString('Balai Wil. X Kupang belum punya penanggung jawab', $teks);
    }

    public function test_nama_unit_kerja_baru_ikut_disamarkan_dari_satuan_kerja_lain(): void
    {
        $this->post(route('master.unit.tambah'), [
            'nama' => 'Balai Pengembangan Kompetensi Pekerjaan Umum dan Perumahan Rakyat Wilayah X Kupang',
            'pendek' => 'Balai Wil. X Kupang',
        ]);

        $bandung = $this->unit('BALAI-4-BANDUNG');
        $this->assertSame('Rekomendasi dikirim ke Balai Wil. IV Bandung',
            Terlihat::teksUntuk('Rekomendasi dikirim ke Balai Wil. IV Bandung, Balai Wil. X Kupang', $bandung));
        $this->assertSame('Rekomendasi dikirim ke satuan kerja yang dituju',
            Terlihat::teksUntuk('Rekomendasi dikirim ke Balai Wil. X Kupang', $this->unit('BALAI-1-MEDAN')));
    }

    public function test_hanya_setba_yang_mengatur_unit_kerja(): void
    {
        $bandung = $this->unit('BALAI-4-BANDUNG');
        $this->masuk('medan');
        $this->post(route('master.unit.tambah'), ['nama' => 'Coba'])->assertForbidden();
        $this->post(route('master.unit.saklar', $bandung))->assertForbidden();
        $this->post(route('master.unit.pj', $bandung), ['nip' => '1'])->assertForbidden();
        $this->assertTrue($bandung->fresh()->aktif);
    }

    public function test_profil_satuan_kerja_menyebut_penanggung_jawabnya(): void
    {
        $medan = $this->akun('medan');
        $isi = $this->masuk('medan')->get(route('rekomendasi.index'))->assertOk()->getContent();

        $this->assertStringContainsString('<b>'.$medan->name.'</b>', $isi);
        $this->assertStringContainsString('<span>Balai Wil. I Medan</span>', $isi);
    }
}
