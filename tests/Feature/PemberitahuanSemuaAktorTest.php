<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Models\DrafLaporan;
use App\Models\KategoriTemuan;
use App\Models\Notifikasi;
use App\Models\Referensi;
use App\Models\Sasaran;
use App\Models\Satker;
use App\Models\User;
use App\Notifications\PemberitahuanEmail;
use App\Support\Pemberitahuan;
use App\Support\Rangka;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/**
 * Pemberitahuan tiap langkah alur untuk tiap aktor: lonceng (lewat halamannya sendiri) dan email.
 */
class PemberitahuanSemuaAktorTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    private Sasaran $baris;

    /** @var array<string, User> */
    private array $aktor;

    private int $batas = 0;

    /** Pemberitahuan terakhir dari data contoh, sebelum alur uji berjalan. */
    private int $awal = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        Notification::fake();

        $this->baris = Sasaran::with('tindakan.rekomendasi', 'satker')->get()
            ->first(fn ($x) => $x->pos() === PosisiBerkas::SATKER
                && $x->tindakan->rekomendasi->jenis()->melewatiSiptl() && $x->satker->penanggungJawab);
        $lain = Satker::whereKeyNot($this->baris->satker_id)->whereHas('penanggungJawab')->firstOrFail();

        $pimpinan = $this->akun('pimpinan@contoh.test');
        $pimpinan->forceFill(['pimpinan_satker_ids' => [$this->baris->satker_id]])->save();

        $this->aktor = [
            'setba' => $this->akun('setba@contoh.test'),
            'admin' => $this->akun('admin@contoh.test'),
            'uki' => $this->akun('uki@contoh.test'),
            'itjen' => $this->akun('inspektorat@contoh.test'),
            'dti' => $this->akun('dti@contoh.test'),
            'pimpinan' => $pimpinan,
            'pimpinanLain' => User::forceCreate(['name' => 'Pimpinan unit lain', 'email' => 'pimpinan.lain@contoh.test',
                'password' => 'rahasia123', 'peran' => 'pimpinan', 'pimpinan_satker_ids' => [$lain->id], 'aktif' => true]),
            'satker' => $this->baris->satker->penanggungJawab,
            'satkerLain' => $lain->penanggungJawab,
        ];
    }

    /** Semua akun pusat menyalakan email; penanggung jawab satuan kerja menyala bawaannya. */
    private function nyalakanEmailPusat(): void
    {
        foreach ($this->aktor as $nama => $u) {
            if (! str_starts_with($nama, 'satker')) {
                $u->forceFill(['pengaturan' => ['email' => true]])->save();
            }
        }
    }

    private function segar(): Sasaran
    {
        return Sasaran::with('tindakan.rekomendasi', 'satker')->find($this->baris->id);
    }

    private function sebagai(string $nama): static
    {
        Rangka::lupakan();

        return $this->actingAs($this->aktor[$nama]);
    }

    /** Pemberitahuan yang tertulis sesudah titik ini. */
    private function langkah(callable $kerja): Collection
    {
        $this->batas = (int) Notifikasi::max('id');
        $kerja();

        return Notifikasi::where('id', '>', $this->batas)->get();
    }

    /**
     * Siapa yang menemukannya di lonceng (rute ringkas dan panel) dan siapa yang menerima email.
     *
     * @param  list<string>  $lonceng
     * @param  list<string>  $email
     */
    private function periksa(string $judul, Collection $baru, array $lonceng, array $email): void
    {
        $this->assertNotEmpty($baru, $judul.': tidak ada pemberitahuan tertulis');
        $id = $baru->pluck('id');

        foreach ($this->aktor as $nama => $u) {
            $lihat = Pemberitahuan::untuk($u->fresh())->whereIn('id', $id);
            $this->assertSame(in_array($nama, $lonceng, true), $lihat->isNotEmpty(), "{$judul}: lonceng {$nama}");

            $kirim = Notification::sent($u, PemberitahuanEmail::class)
                ->filter(fn ($n) => $id->contains($n->pemberitahuan->id));
            $this->assertSame(in_array($nama, $email, true), $kirim->isNotEmpty(), "{$judul}: email {$nama}");
            /* Tiap email bisa digambar, dengan link ke pemberitahuannya. */
            foreach ($kirim as $n) {
                $mail = $n->toMail($u->fresh());
                $this->assertSame(route('pemberitahuan.buka', $n->pemberitahuan), $mail->actionUrl);
                /* Beridentitas infaX dan berbahasa Indonesia, bukan bawaan kerangka. */
                $html = (string) $mail->render();
                $this->assertSame('infaX', $mail->from[1]);
                $this->assertStringContainsString('infaX', $html);
                $this->assertStringContainsString('salin alamat berikut ke peramban', $html);
                foreach (['Laravel', 'laravel.com', 'If you', 'All rights reserved'] as $asing) {
                    $this->assertStringNotContainsString($asing, $html, "{$judul}: email {$nama}");
                }
                /* Logo disematkan, bukan dimuat dari situs luar; versi teks biasa ikut lengkap. */
                $this->assertStringContainsString('src="data:image/png', $html);
                $teks = view($mail->view['text'], $mail->data())->render();
                $this->assertStringContainsString($mail->actionUrl, $teks);
                $this->assertStringContainsString('infaX', $teks);
            }
        }
    }

    private function kirimDariSatker(): void
    {
        $baris = $this->segar();
        $rek = $baris->tindakan->rekomendasi->load('permintaanDokumen.item');
        $bukti = $rek->permintaanUntuk($baris->satker_id, $baris->tindakan_id)
            ->flatMap->item->where('terpenuhi', false)
            ->map(fn ($i) => ['nama' => 'Bukti '.$i->nama, 'link' => 'https://contoh.test/'.$i->id, 'jenis' => $i->nama, 'untuk' => $i->id])
            ->values()->all();

        $this->sebagai('satker')->post(route('tanggapan.simpan', $baris), [
            'aksi' => 'kirim', 'uraian' => 'Tindak lanjut sudah dilaksanakan.', 'bukti' => $bukti,
        ])->assertRedirect();
    }

    private function teruskan(): void
    {
        $this->sebagai('setba')->post(route('sasaran.teruskan', $this->segar()), [
            'nomor' => 'PW.02.02-Sb/'.random_int(1000, 9999), 'tanggal' => now()->toDateString(), 'perihal' => 'Penyampaian tindak lanjut',
        ])->assertRedirect();
    }

    private function putus(string $oleh, string $hasil, array $isi = []): void
    {
        $this->sebagai($oleh)->post(route('sasaran.putus', $this->segar()), ['hasil' => $hasil] + $isi + ($hasil === 'M'
            ? ['nomor' => '099/'.$oleh.'/2026', 'tgl_surat' => now()->toDateString(), 'catatan' => 'Sudah sesuai.']
            : []))->assertRedirect();
    }

    public function test_tiap_langkah_alur_sampai_ke_aktor_yang_tepat_di_lonceng_dan_email(): void
    {
        $this->nyalakanEmailPusat();
        $this->awal = (int) Notifikasi::max('id');
        /* Admin DTI hanya diberi tahu perubahan sistem; pimpinan diberi tahu keputusan UKI, Itjen, SIPTL. */
        $pusatSetba = ['setba'];
        $putusan = ['setba', 'satker', 'pimpinan'];
        $siptl = ['satker', 'uki', 'itjen', 'pimpinan'];

        $this->periksa('Satker mengirim', $this->langkah(fn () => $this->kirimDariSatker()), $pusatSetba, $pusatSetba);
        $this->periksa('Setba meneruskan ke UKI', $this->langkah(fn () => $this->teruskan()), ['uki', 'satker'], ['uki', 'satker']);

        $baru = $this->langkah(fn () => $this->putus('uki', 'BM',
            ['catatan' => 'Bukti yang dilampirkan belum bernomor.', 'dokumen' => ['Surat bernomor']]));
        $this->periksa('UKI belum memadai', $baru, $putusan, $putusan);
        /* Satuan kerja hanya diberi tahu status dan tempat berkasnya; alasannya menyusul. */
        $untukSatker = Pemberitahuan::untuk($this->aktor['satker'])->whereIn('id', $baru->pluck('id'));
        $this->assertStringNotContainsString('belum bernomor', $untukSatker->pluck('aksi')->join(' '));
        foreach (['satker', 'pimpinan'] as $nama) {
            $this->assertStringNotContainsString('belum bernomor',
                Pemberitahuan::untuk($this->aktor[$nama])->whereIn('id', $baru->pluck('id'))->pluck('aksi')->join(' '));
            foreach (Notification::sent($this->aktor[$nama], PemberitahuanEmail::class) as $n) {
                $this->assertStringNotContainsString('belum bernomor', implode(' ', $n->toMail($this->aktor[$nama])->introLines));
            }
        }

        $this->periksa('Setba mengirim ulang', $this->langkah(fn () => $this->sebagai('setba')
            ->post(route('sasaran.kirimUlang', $this->segar()), ['keterangan' => 'Mohon dilengkapi.', 'dokumen' => ['Surat bernomor']])
            ->assertRedirect()), ['satker'], ['satker']);

        $this->periksa('Satker mengirim lagi', $this->langkah(fn () => $this->kirimDariSatker()), $pusatSetba, $pusatSetba);
        $this->teruskan();
        $this->periksa('UKI memadai', $this->langkah(fn () => $this->putus('uki', 'M')), $putusan, $putusan);
        $this->periksa('Setba meneruskan ke Inspektorat', $this->langkah(fn () => $this->teruskan()), ['itjen', 'satker'], ['itjen', 'satker']);
        $this->periksa('Inspektorat memadai', $this->langkah(fn () => $this->putus('itjen', 'M')), $putusan, $putusan);
        $this->assertSame(PosisiBerkas::TUNTAS, $this->segar()->pos());

        $this->periksa('Unggah SIPTL', $this->langkah(fn () => $this->sebagai('setba')
            ->post(route('siptl.unggah', $this->segar()), ['tanggal' => now()->toDateString()])->assertRedirect()), $siptl, $siptl);
        $this->periksa('Putusan BPK', $this->langkah(fn () => $this->sebagai('setba')
            ->post(route('siptl.status', $this->segar()), ['status' => 'BS', 'catatan' => 'NTPN belum ada.'])->assertRedirect()), $siptl, $siptl);
        $this->periksa('Tindak lanjut ulang BPK', $this->langkah(fn () => $this->sebagai('setba')
            ->post(route('siptl.ulangBpk', $this->segar()->tindakan->rekomendasi), [
                'sasaran_id' => $this->baris->id, 'alasan' => 'BPK meminta NTPN.', 'dokumen' => ['Bukti setor dengan NTPN'],
            ])->assertRedirect()), $siptl, $siptl);

        $this->periksaLonceng();
    }

    /**
     * Bentuk pemberitahuan di layar tiap aktor: angka dan pop-up (ringkas), panel lonceng, halaman,
     * membuka satu pemberitahuan, dan menandai semua dibaca.
     */
    private function periksaLonceng(): void
    {
        foreach ($this->aktor as $nama => $u) {
            /* Tiap aktor = sesi sendiri: tanda pop-up aktor sebelumnya dibuang. */
            session()->forget('popup_ditampilkan');
            $punya = Pemberitahuan::untuk($u->fresh())->isNotEmpty();
            $dariAlur = Pemberitahuan::untuk($u->fresh())->where('id', '>', $this->awal)->isNotEmpty();
            $harap = Pemberitahuan::jumlahBaru($u->fresh());
            if ($dariAlur) {
                $this->assertGreaterThan(0, $harap, "angka lonceng {$nama}");
            }

            $ringkas = $this->sebagai($nama)->getJson(route('pemberitahuan.ringkas'))->assertOk()->json();
            $this->assertSame($harap, $ringkas['baru'], "angka lonceng {$nama}");
            $this->assertSame($harap > 0, $ringkas['popup'] !== null, "pop-up {$nama}");
            /* Pop-up sekali per sesi. */
            $this->assertNull($this->sebagai($nama)->getJson(route('pemberitahuan.ringkas'))->json('popup'), "pop-up kedua {$nama}");

            $panel = $this->sebagai($nama)->post(route('pemberitahuan.panel'))->assertOk()->getContent();
            $this->assertSame(0, $this->sebagai($nama)->getJson(route('pemberitahuan.ringkas'))->json('baru'), "angka sesudah panel {$nama}");
            $hal = $this->sebagai($nama)->get(route('pemberitahuan'));
            $this->assertSame(200, $hal->status(), "halaman {$nama} → ".$hal->headers->get('Location'));
            if (! $punya) {
                continue;
            }

            $k = Pemberitahuan::untuk($u->fresh())->first();
            $this->assertStringContainsString(e(str($k->aksi)->limit(30, '')->toString()), $panel, "isi panel {$nama}");
            $tuju = $this->sebagai($nama)->get(route('pemberitahuan.buka', $k))->assertRedirect()->headers->get('Location');
            $buka = $this->sebagai($nama)->get($tuju);
            $this->assertSame(200, $buka->status(), "buka {$nama} {$tuju} → ".$buka->headers->get('Location'));
            $this->assertTrue(Pemberitahuan::sudahDibaca($k->fresh('dibaca'), $u), "dibuka = dibaca {$nama}");

            $this->sebagai($nama)->withHeader('X-Requested-With', 'XMLHttpRequest')
                ->postJson(route('pemberitahuan.semua'))->assertOk()->assertJson(['belum' => 0, 'baru' => 0]);
        }

        /* Pemberitahuan aktor lain tidak bisa dibuka lewat alamatnya. */
        $milikUki = Notifikasi::whereJsonContains('untuk_peran', 'uki')->whereJsonDoesntContain('untuk_peran', 'satker')->first()
            ?? Notifikasi::whereJsonContains('untuk_peran', 'uki')->firstOrFail();
        foreach (['dti', 'pimpinanLain', 'satkerLain'] as $nama) {
            $this->sebagai($nama)->get(route('pemberitahuan.buka', $milikUki))->assertForbidden();
        }
    }

    public function test_link_pemberitahuan_peran_lain_menawarkan_beralih_bukan_403(): void
    {
        /* Penanggung jawab yang juga Setba; peran aktifnya sekarang Satuan kerja. */
        $pj = $this->aktor['satker'];
        $pj->forceFill(['peran_tambahan' => ['setba']])->save();
        $rek = Sasaran::where('satker_id', $this->aktor['satkerLain']->satker_id)->firstOrFail()->tindakan->rekomendasi;
        $k = Pemberitahuan::tulis($rek, 'Berkas dikirim ke Setba', [PeranPengguna::SETBA], 'r-tindaklanjut',
            [$this->aktor['satkerLain']->satker_id]);
        $this->app->terminate();
        Notification::assertSentTo($pj, PemberitahuanEmail::class, fn ($n) => $n->pemberitahuan->is($k));

        $alamat = route('pemberitahuan.buka', $k, false);
        $this->actingAs($pj)->get(route('pemberitahuan.buka', $k))->assertOk()
            ->assertSee('Pemberitahuan ini untuk peran')->assertSee('Beralih ke Setba dan buka')
            ->assertSee('name="kembali" value="'.$alamat.'"', false);
        $this->assertFalse(Pemberitahuan::sudahDibaca($k->fresh('dibaca'), $pj));

        $this->post(route('akun.peran-aktif'), ['peran' => 'setba', 'kembali' => $alamat])->assertRedirect(url($alamat));
        $this->get($alamat)->assertRedirect();
        $this->assertTrue(Pemberitahuan::sudahDibaca($k->fresh('dibaca'), $pj));

        /* Alamat kembali selain pemberitahuan diabaikan. */
        $this->post(route('akun.peran-aktif'), ['peran' => 'satker', 'kembali' => 'https://contoh.test/curang'])
            ->assertRedirect(route('beranda'));
        /* Tanpa peran yang berhak tetap ditolak. */
        $this->actingAs($this->aktor['satkerLain'])->get(route('pemberitahuan.buka', $k))->assertForbidden();
    }

    /** Jam peragaan berulang tiap hari: pemberitahuan bisa berjam di depan jam sekarang. */
    public function test_membuka_panel_menghabiskan_angka_baru_walau_jamnya_di_depan(): void
    {
        $this->kirimDariSatker();
        $this->teruskan();
        Notifikasi::where('id', '>', $this->awal)->update(['waktu' => now()->addHours(3)]);
        $this->assertGreaterThan(0, Pemberitahuan::jumlahBaru($this->aktor['satker']->fresh()));

        $this->sebagai('satker')->post(route('pemberitahuan.panel'))->assertOk();
        $this->assertSame(0, Pemberitahuan::jumlahBaru($this->aktor['satker']->fresh()));
    }

    /** Perubahan sistem oleh Admin DTI lain: lonceng dan email Admin, bukan pelakunya, bukan peran lain. */
    public function test_admin_diberi_tahu_perubahan_sistem_oleh_admin_lain(): void
    {
        $this->nyalakanEmailPusat();
        $adminLain = User::forceCreate(['name' => 'Admin DTI kedua', 'email' => 'admin.dua@contoh.test',
            'password' => 'rahasia123', 'peran' => 'admin', 'aktif' => true, 'pengaturan' => ['email' => true]]);
        $this->aktor['adminLain'] = $adminLain;
        $unit = $this->baris->satker;

        $baru = $this->langkah(fn () => $this->sebagai('admin')->post(route('master.unit.simpan', $unit),
            ['nama' => $unit->nama, 'pendek' => $unit->nama_pendek, 'kode_satker' => '694101'])->assertRedirect());
        $this->periksa('Admin mengubah kode satker', $baru, ['adminLain'], ['adminLain']);
        $k = $baru->sole();
        $this->assertTrue($k->sistem());
        $this->assertSame($this->aktor['admin']->name, $k->label_pelaku);
        $this->assertSame('Satuan kerja', Pemberitahuan::sebutSatker($k, $adminLain));

        /* Email-nya menyebut isi perubahannya; tombolnya ke Log admin. */
        $mail = Notification::sent($adminLain, PemberitahuanEmail::class)->last()->toMail($adminLain);
        $this->assertStringStartsWith('Perubahan sistem — Mengubah kode satker', $mail->subject);
        $this->assertContains('Kode satker: — → 694101', $mail->viewData['barisPesan']);
        $this->assertStringContainsString('Perubahan sistem', (string) $mail->render());

        /* Panel dan membuka pemberitahuannya: langsung ke entri log. */
        $this->sebagai('adminLain')->post(route('pemberitahuan.panel'))->assertOk()
            ->assertSee('Perubahan sistem · Satuan kerja')->assertSee('Mengubah kode satker');
        $this->sebagai('adminLain')->get(route('pemberitahuan.buka', $k))
            ->assertRedirect(route('log', ['kejadian' => $k->log_aktivitas_id, 'rinci' => $k->log_aktivitas_id]));
        $this->sebagai('adminLain')->get(route('log', ['kejadian' => $k->log_aktivitas_id, 'rinci' => $k->log_aktivitas_id]))->assertOk();

        /* Mengunduh bukan perubahan; Setba tidak bisa membuka pemberitahuan Admin. */
        $this->assertCount(0, $this->langkah(fn () => $this->sebagai('admin')->get(route('master.unit.unduh'))->assertOk()));
        $this->sebagai('setba')->get(route('pemberitahuan.buka', $k))->assertForbidden();
    }

    public function test_bawaan_email_hanya_penanggung_jawab_dan_yang_dimatikan_tetap_di_lonceng(): void
    {
        $this->kirimDariSatker();
        $this->periksa('Bawaan, diteruskan ke UKI', $this->langkah(fn () => $this->teruskan()), ['uki', 'satker'], ['satker']);

        /* Penanggung jawab mematikan email: pemberitahuan tetap ada di loncengnya. */
        $this->aktor['satker']->forceFill(['pengaturan' => ['email' => false]])->save();
        $this->periksa('Email satker mati', $this->langkah(fn () => $this->putus('uki', 'M')), ['setba', 'satker', 'pimpinan'], []);
    }

    public function test_rekomendasi_baru_diberitahukan_ke_tiap_satuan_kerja_yang_dituju(): void
    {
        $medan = Satker::where('nama_pendek', 'Balai Wil. I Medan')->firstOrFail();
        $poli = Satker::where('nama_pendek', 'Politeknik PU')->firstOrFail();
        $this->aktor['satker'] = $medan->penanggungJawab;
        $this->aktor['satkerLain'] = $poli->penanggungJawab;
        $this->aktor['pimpinan']->forceFill(['pimpinan_satker_ids' => [$medan->id]])->save();
        $this->nyalakanEmailPusat();

        $baru = $this->langkah(function () use ($medan, $poli) {
            $this->sebagai('setba')->post(route('laporan.baru.simpan'), [
                'aksi' => 'simpan',
                'surat' => ['sumber' => 'LHP', 'nomor' => '78/LHP/XVIII/09/2026', 'tgl_surat' => '2026-08-01', 'tgl_terima' => '2026-08-05'],
                'berkas' => ['judul' => 'Laporan 78', 'link' => 'https://contoh.test/78.pdf'],
            ])->assertRedirect(route('laporan.baru'));
            $d = DrafLaporan::where('user_id', $this->aktor['setba']->id)->firstOrFail()->isian;
            $t = $d['temuan'][0];
            $r = $t['rekom'][0];
            $this->post(route('laporan.baru.simpan'), ['aksi' => 'maju', 'tem' => [$t['id'] => [
                'nomor' => '1.1', 'judul' => 'Honorarium melebihi standar', 'sebab' => 'Verifikasi kurang.', 'akibat' => 'Kelebihan bayar.',
                'kategori' => (string) KategoriTemuan::where('sumber', 'LHP')->where('nama', 'Kepatuhan')->firstOrFail()->id,
                'intern' => $t['intern'], 'satker' => [$medan->id, $poli->id],
                'rekom' => [$r['id'] => [
                    'uraian' => 'Menarik kelebihan pembayaran dan menyetorkannya ke kas negara.', 'ref_lhp' => 'I.1.1.a',
                    'sifat' => (string) Referensi::where('jenis', 'sifat_rekom')->where('nama', 'Informasi kerugian negara')->firstOrFail()->id,
                    'tindakan' => [$r['tindakan'][0]['id'] => [
                        'bentuk' => 'Penyetoran ke kas negara', 'satker' => [$medan->id, $poli->id],
                        'nilai' => [$medan->id => '7500000', $poli->id => '12500000'],
                        'dokumen' => ['Bukti setor ke kas negara (SSBP)'], 'tgl_renaksi' => '2026-10-04', 'target' => '', 'catatan' => '',
                    ]],
                ]],
            ]]])->assertRedirect(route('laporan.baru'));
            $this->post(route('laporan.baru.simpan'), ['aksi' => 'ajukan'])->assertRedirect();
        });

        $this->assertCount(2, $baru);
        $this->periksa('Rekomendasi baru', $baru, ['satker', 'satkerLain'], ['satker', 'satkerLain']);
        /* Tiap satuan kerja hanya menerima bebannya sendiri. */
        foreach (['satker' => $medan, 'satkerLain' => $poli] as $nama => $unit) {
            $this->assertSame([$unit->id], Pemberitahuan::untuk($this->aktor[$nama])->whereIn('id', $baru->pluck('id'))
                ->flatMap->satker->pluck('id')->unique()->values()->all());
        }
    }

    public function test_satu_alamat_email_gagal_tidak_menghentikan_penerima_lain(): void
    {
        $this->nyalakanEmailPusat();
        $this->kirimDariSatker();
        $this->teruskan();
        /* Penerima pertama (penanggung jawab) gagal dikirimi; UKI tetap menerima. */
        Notification::swap(new class(app()) extends \Illuminate\Support\Testing\Fakes\NotificationFake
        {
            public function __construct($app) {}

            public function sendNow($notifiables, $notification, ?array $channels = null)
            {
                foreach ((array) (is_iterable($notifiables) ? collect($notifiables)->all() : [$notifiables]) as $n) {
                    if ($n->peran?->value === 'satker') {
                        throw new \RuntimeException('Server email menolak alamat ini.');
                    }
                }
                parent::sendNow($notifiables, $notification, $channels);
            }
        });
        $this->langkah(fn () => $this->putus('uki', 'M'));

        Notification::assertSentTo($this->aktor['setba'], PemberitahuanEmail::class);
    }
}
