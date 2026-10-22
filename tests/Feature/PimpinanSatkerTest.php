<?php

namespace Tests\Feature;

use App\Enums\PeranPengguna as P;
use App\Models\Lampiran;
use App\Models\LogAktivitas;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Models\Satker;
use App\Models\User;
use App\Support\DirektoriIrm;
use App\Support\Pemberitahuan;
use App\Support\Terlihat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\PakaiDataContoh;
use Tests\TestCase;

class PimpinanSatkerTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    /** Satker pertama yang dipimpin (akun contoh memimpin satu Satker). */
    private function unit(User $u): ?int
    {
        return $u->satkerDipimpin()[0] ?? null;
    }

    private function pimpinan(): User
    {
        return $this->akun('pimpinan@contoh.test');
    }

    private function bersama(User $u): Rekomendasi
    {
        return Rekomendasi::with('sasaran.satker', 'temuan.laporan')->get()->firstOrFail(
            fn ($r) => $r->dituju($this->unit($u)) && $r->daftarSasaran()->pluck('satker_id')->unique()->count() > 1);
    }

    public function test_dashboard_hanya_menghitung_penugasan_unit_yang_dipimpin(): void
    {
        $u = $this->pimpinan();
        $asing = Satker::whereKeyNot($this->unit($u))->firstOrFail();
        $expected = Sasaran::where('satker_id', $this->unit($u))->count();
        foreach ([[], ['satker' => [$asing->id], 'hal' => 'tabel'], ['ubah' => 'satker:'.$asing->id]] as $query) {
            /* Jenis laporan selalu satu: LHP dan LHA dihitung terpisah, jumlahnya seluruh penugasan unit. */
            $jumlah = 0;
            foreach (['LHP', 'LHA'] as $jenis) {
                $res = $this->actingAs($u)->get(route('ringkasan', $query + ['jenis' => $jenis]));
                if ($res->isRedirection()) {
                    $res = $this->get($res->headers->get('Location'));
                }
                $res->assertOk()->assertSee($u->satkerDipimpinModel()->first()->namaPendek())->assertDontSee($asing->namaPendek());
                $items = $res->viewData('d')['K'];
                $jumlah += array_sum(array_map(fn ($x) => $x['baris']->count(), $items));
                foreach ($items as $x) {
                    $this->assertSame($jenis, $x['lap']->sumber->value);
                    $this->assertTrue($x['baris']->every(fn ($b) => $b->satker_id === $this->unit($u)));
                }
            }
            $this->assertSame($expected, $jumlah);
        }
    }

    public function test_filter_satker_dashboard_hanya_unit_yang_dipantau_tanpa_semua(): void
    {
        $bagian = function (string $isi) {
            $mulai = strpos($isi, 'id="dsb-l-satker"');

            return substr($isi, $mulai, strpos($isi, '</section>', $mulai) - $mulai);
        };

        $u = $this->pimpinan();
        $isi = $bagian($this->actingAs($u)->get(route('ringkasan', ['jenis' => 'semua']))->assertOk()->getContent());
        $this->assertStringContainsString('class="keping-tetap"', $isi);
        $this->assertStringContainsString($u->satkerDipimpinModel()->first()->namaPendek(), $isi);
        $this->assertStringNotContainsString('>Semua<', $isi);

        /* Pembaca lintas unit tetap punya "Semua". */
        $isi = $bagian($this->actingAs($this->akun('setba@contoh.test'))->get(route('ringkasan', ['jenis' => 'semua']))->getContent());
        $this->assertStringContainsString('>Semua<', $isi);
        $this->assertStringNotContainsString('keping-tetap', $isi);
    }

    public function test_kemajuan_per_satker_untuk_pusat_dan_per_tindak_lanjut_untuk_pimpinan(): void
    {
        $u = $this->pimpinan();
        $r = $this->bersama($u);
        $nSatker = $r->daftarSasaran()->pluck('satker_id')->unique()->count();
        $nMilik = $r->daftarSasaran()->where('satker_id', $this->unit($u))->count();

        $this->assertMatchesRegularExpression('/\d+ dari '.$nSatker.' Satker selesai/',
            $this->actingAs($this->akun('setba@contoh.test'))->get(route('laporan.show', $r->temuan->laporan))->assertOk()->getContent());
        $this->assertMatchesRegularExpression('/\d+ dari '.$nMilik.' tindak lanjut selesai/',
            $this->actingAs($u)->get(route('laporan.show', $r->temuan->laporan))->assertOk()->getContent());
    }

    public function test_daftar_dan_rekomendasi_bersama_hanya_menampilkan_unitnya(): void
    {
        $u = $this->pimpinan();
        $r = $this->bersama($u);
        $lain = $r->daftarSasaran()->reject(fn ($x) => $x->satker_id === $this->unit($u))->map->satker->unique('id');
        $res = $this->actingAs($u)->get(route('rekomendasi.show', $r))->assertOk();
        foreach ($lain as $s) {
            $res->assertDontSee($s->nama)->assertDontSee($s->namaPendek());
        }
        $visible = $res->viewData('r');
        $this->assertTrue($visible->daftarSasaran()->every(fn ($s) => $s->satker_id === $this->unit($u)));
        $this->assertTrue($visible->semuaBaris()->every(fn ($s) => $s->satker_id === $this->unit($u)));
        $this->assertSame((int) $visible->daftarSasaran()->sum('nilai'), $visible->nilai_pulih);
        $index = $this->get(route('rekomendasi.index', ['keadaan' => 'semua']))->assertOk();
        $this->assertTrue($index->viewData('daftar')->every(fn ($r) => $r->dituju($this->unit($u))));
        $this->assertCount(0, $index->viewData('daftarSatker'));
    }

    public function test_laporan_dan_pencarian_tidak_membuka_data_unit_lain(): void
    {
        $u = $this->pimpinan();
        $scope = Terlihat::untuk($u);
        $asing = Rekomendasi::with('sasaran', 'temuan.laporan')->get()->firstOrFail(fn ($r) => ! $r->dituju($this->unit($u)));
        $this->actingAs($u)->get(route('rekomendasi.show', $asing))->assertForbidden();
        $lap = $scope->laporan()->pluck('id');
        $this->get(route('laporan.index', ['keadaan' => 'semua']))->assertOk()
            ->assertViewHas('semua', fn ($all) => $all->pluck('id')->diff($lap)->isEmpty());
        foreach ($scope->daftarLaporan() as $l) {
            $res = $this->get(route('laporan.show', $l))->assertOk();
            foreach ($res->viewData('lap')->temuan as $t) {
                foreach ($t->rekomendasi as $r) {
                    $this->assertTrue($r->daftarSasaran()->every(fn ($s) => $s->satker_id === $this->unit($u)));
                }
            }
        }
        $bukanLaporan = \App\Models\Laporan::whereNotIn('id', $lap)->firstOrFail();
        $this->get(route('laporan.show', $bukanLaporan))->assertForbidden();
        $this->getJson(route('cari', ['q' => $asing->kode]))->assertOk()->assertJsonPath('jumlah', 0);
    }

    public function test_berkas_asing_dan_surat_lintas_unit_tetap_ditolak(): void
    {
        $u = $this->pimpinan();
        $r = $this->bersama($u);
        $asli = Lampiran::where('rekomendasi_id', $r->id)->where('surat_asli', true)->firstOrFail();
        $lain = Lampiran::whereNotNull('sasaran_id')->get()->firstOrFail(fn ($b) => $b->sasaran?->satker_id !== $this->unit($u));
        $this->actingAs($u)->get(route('berkas.show', $asli))->assertForbidden();
        $this->get(route('berkas.show', $lain))->assertForbidden();
        $this->get(route('berkas.buka', $lain))->assertForbidden();
        $kosong = Lampiran::create(['nama_asli' => 'Tanpa cakupan.pdf']);
        $this->get(route('berkas.show', $kosong))->assertForbidden();
    }

    public function test_pimpinan_tanpa_cakupan_tidak_mendapat_akses_global(): void
    {
        $u = $this->pimpinan();
        $u->forceFill(['pimpinan_satker_ids' => null])->save();
        $scope = Terlihat::untuk($u);
        $this->assertSame(0, $scope->rekomendasi()->count());
        $this->assertSame(0, $scope->laporan()->count());
        $this->assertSame(0, $scope->satkers()->count());
        $this->actingAs($u)->get(route('ringkasan'))->assertOk()->assertSee('Satker belum ditentukan');
        $this->get(route('rekomendasi.index'))->assertOk()->assertViewHas('daftar', fn ($r) => $r->isEmpty());
        $this->get(route('laporan.index'))->assertOk()->assertViewHas('semua', fn ($l) => $l->isEmpty());
        $this->get(route('rekomendasi.show', Rekomendasi::firstOrFail()))->assertForbidden();
        $this->get(route('akun'))->assertOk();
    }

    public function test_pimpinan_hanya_memantau_tidak_mengirim_tindak_lanjut(): void
    {
        $u = $this->pimpinan();
        $s = Sasaran::where('satker_id', $this->unit($u))->firstOrFail();
        $lama = $s->getAttributes();
        $this->actingAs($u)->post(route('tanggapan.simpan', $s), ['aksi' => 'draf', 'uraian' => 'coba'])->assertForbidden();
        $this->post(route('sasaran.teruskan', $s))->assertForbidden();
        $this->get(route('master'))->assertForbidden();
        $this->assertSame($lama, $s->fresh()->getAttributes());
    }

    public function test_satker_pj_dan_pimpinan_dapat_berbeda_dan_mengikuti_peran_aktif(): void
    {
        $u = $this->akun('medan');
        $other = $this->akun('bandung')->satker_id;
        $u->forceFill(['peran_tambahan' => ['pimpinan', 'uki'], 'pimpinan_satker_ids' => [$other]])->save();
        $this->actingAs($u)->get(route('rekomendasi.index'))->assertOk()
            ->assertViewHas('daftar', fn ($rs) => $rs->every(fn ($r) => $r->daftarSasaran()->every(fn ($s) => $s->satker_id === $u->satker_id)));
        $this->post(route('akun.peran-aktif'), ['peran' => 'pimpinan'])->assertRedirect();
        $this->get(route('rekomendasi.index'))->assertOk()
            ->assertViewHas('daftar', fn ($rs) => $rs->isNotEmpty() && $rs->every(fn ($r) => $r->daftarSasaran()->every(fn ($s) => $s->satker_id === $other)));
        $this->post(route('akun.peran-aktif'), ['peran' => 'uki'])->assertRedirect();
        $this->get(route('rekomendasi.index', ['keadaan' => 'semua']))->assertOk()
            ->assertViewHas('daftar', fn ($rs) => $rs->count() === Rekomendasi::count());
        $this->assertSame($u->satker_id, $u->fresh()->satker_id);
    }

    public function test_admin_wajib_memilih_unit_untuk_pimpinan_utama_atau_tambahan(): void
    {
        $u = $this->akun('uki@contoh.test');
        $this->masuk('admin@contoh.test')->post(route('master.pengguna.simpan', $u), ['peran' => 'pimpinan'])->assertSessionHasErrors('pimpinan_satker');
        $this->post(route('master.pengguna.simpan', $u), ['perans' => ['uki', 'pimpinan'], 'pimpinan_satker_id' => 999999])->assertSessionHasErrors('pimpinan_satker.0');
        $this->assertSame(P::UKI, $u->fresh()->peran);
        $unit = $this->akun('bandung')->satker_id;
        $this->post(route('master.pengguna.simpan', $u), ['perans' => ['uki', 'pimpinan'], 'pimpinan_satker_id' => $unit])->assertSessionHas('pesan');
        $this->assertSame($unit, $this->unit($u->fresh()));
        $this->assertTrue($u->fresh()->punyaPeran(P::PIMPINAN));
        $this->post(route('master.pengguna.simpan', $u), ['perans' => ['uki']])->assertSessionHas('pesan');
        $this->assertNull($this->unit($u->fresh()));
    }

    public function test_perubahan_cakupan_tanpa_perubahan_peran_dicatat_dan_mengakhiri_sesi(): void
    {
        $u = $this->pimpinan();
        $unit = $this->akun('bandung')->satker;
        $versi = $u->versi_akses;
        $this->masuk('admin@contoh.test')->post(route('master.pengguna.simpan', $u), ['perans' => ['pimpinan'], 'pimpinan_satker_id' => $unit->id])->assertSessionHas('pesan');
        $this->assertSame($unit->id, $this->unit($u->fresh()));
        $this->assertSame($versi + 1, $u->fresh()->versi_akses);
        $log = LogAktivitas::where('aksi', 'master.pengguna.peran')->latest('id')->firstOrFail();
        $this->assertSame($unit->namaPendek(), $log->rincian['sesudah']['satker_pimpinan']);
        $this->post(route('master.pengguna.simpan', $u), ['perans' => ['pimpinan'], 'pimpinan_satker_id' => $unit->id])->assertSessionHas('pesan');
        $this->assertSame($versi + 1, $u->fresh()->versi_akses);
    }

    public function test_tambah_user_dan_impor_pimpinan_membawa_cakupannya(): void
    {
        $p = DirektoriIrm::semua()->firstWhere('nama', 'Siti Rahmawati');
        $unit = $this->akun('bandung')->satker_id;
        $this->masuk('admin@contoh.test')->post(route('master.pengguna.tambah'), ['nip' => $p['nip'], 'perans' => ['uki', 'pimpinan']])->assertSessionHasErrors('pimpinan_satker');
        $this->post(route('master.pengguna.tambah'), ['nip' => $p['nip'], 'perans' => ['uki', 'pimpinan'], 'pimpinan_satker_id' => $unit])->assertSessionHas('pesan');
        $this->assertSame($unit, $this->unit(User::where('nip', $p['nip'])->firstOrFail()));
        $p2 = DirektoriIrm::semua()->firstWhere('nama', 'Dewi Kartikasari');
        $this->post(route('master.pengguna.banyak'), ['nip' => $p2['nip'], 'peran' => 'pimpinan'])->assertSessionHasErrors('pimpinan_satker');
        $this->post(route('master.pengguna.banyak'), ['nip' => $p2['nip'], 'peran' => 'pimpinan', 'pimpinan_satker_id' => $unit])->assertSessionHas('pesan');
        $this->assertSame($unit, $this->unit(User::where('nip', $p2['nip'])->firstOrFail()));
        $this->get(route('master', ['tab' => 'pengguna', 'jendela' => 'akun-'.$this->pimpinan()->id]))->assertOk()->assertSee('Satker yang dipimpin');
    }

    public function test_notifikasi_pimpinan_tidak_menyebut_unit_lain(): void
    {
        $u = $this->pimpinan();
        $r = $this->bersama($u);
        $other = $r->daftarSasaran()->first(fn ($s) => $s->satker_id !== $this->unit($u));
        $this->masuk('setba@contoh.test');
        $notifikasi = Pemberitahuan::tulis($r, 'Berkas diperiksa', [P::PIMPINAN], null, [$other->satker_id]);
        $this->assertFalse(Pemberitahuan::boleh($notifikasi, $u));
        $this->actingAs($u)->get(route('pemberitahuan.buka', $notifikasi))->assertForbidden();
    }

    public function test_perintah_server_tidak_membuat_pimpinan_global(): void
    {
        $p = DirektoriIrm::semua()->firstWhere('nama', 'Dewi Kartikasari');
        $this->assertSame(1, Artisan::call('simtlhp:pengguna', ['nip' => $p['nip'], 'peran' => 'pimpinan']));
        $this->assertFalse(User::where('nip', $p['nip'])->exists());
        $unit = $this->akun('bandung')->satker_id;
        $this->assertSame(0, Artisan::call('simtlhp:pengguna', ['nip' => $p['nip'], 'peran' => 'pimpinan', '--satker' => $unit]));
        $this->assertSame($unit, $this->unit(User::where('nip', $p['nip'])->firstOrFail()));
    }

    /* ---- Pimpinan beberapa Satker / Semua Satker ---- */

    /** Pimpinan Medan juga memimpin satu Satker lain pada rekomendasi bersama. */
    private function pimpinanDua(): array
    {
        $u = $this->pimpinan();
        $r = $this->bersama($u);
        $lain = $r->daftarSasaran()->pluck('satker_id')->first(fn ($id) => $id !== $this->unit($u));
        $dua = [$this->unit($u), $lain];
        sort($dua);
        $u->forceFill(['pimpinan_satker_ids' => $dua])->save();

        return [$u->fresh(), $r, $dua];
    }

    public function test_pimpinan_beberapa_satker_melihat_gabungan_satkernya_saja(): void
    {
        [$u, $r, $dua] = $this->pimpinanDua();
        $this->assertSame($dua, $u->satkerDipimpin());

        $harap = Rekomendasi::whereHas('sasaran', fn ($s) => $s->whereIn('satker_id', $dua))->count();
        $index = $this->actingAs($u)->get(route('rekomendasi.index', ['keadaan' => 'semua']))->assertOk();
        $this->assertCount($harap, $index->viewData('daftar'));
        $this->assertEqualsCanonicalizing($dua, $index->viewData('daftarSatker')->pluck('id')->all());
        $index->assertSee('Semua Satker yang dipimpin');

        /* Rincian: baris kedua Satker tampil, Satker lain tidak; status dari baris Satker-nya. */
        $res = $this->get(route('rekomendasi.show', $r))->assertOk();
        $tampil = $res->viewData('r');
        $this->assertEqualsCanonicalizing($dua, $tampil->daftarSasaran()->pluck('satker_id')->unique()->values()->all());
        $this->assertTrue($tampil->semuaBaris()->every(fn ($s) => in_array($s->satker_id, $dua, true)));
        foreach ($r->daftarSasaran()->whereNotIn('satker_id', $dua)->map->satker->unique('id') as $s) {
            $res->assertDontSee($s->namaPendek());
        }

        /* Kemajuan per Satker; filter dashboard hanya Satker-nya, tanpa Semua. */
        $this->assertMatchesRegularExpression('/\d+ dari 2 Satker selesai/',
            $this->get(route('laporan.show', $r->temuan->laporan))->assertOk()->getContent());
        $isi = $this->get(route('ringkasan', ['jenis' => 'semua']))->assertOk()->getContent();
        $mulai = strpos($isi, 'id="dsb-l-satker"');
        $filter = substr($isi, $mulai, strpos($isi, '</section>', $mulai) - $mulai);
        foreach (Satker::whereKey($dua)->get() as $s) {
            $this->assertStringContainsString(e($s->namaPendek()), $filter);
        }
        $this->assertStringNotContainsString('>Semua<', $filter);
        $this->assertStringNotContainsString('keping-tetap', $filter);
        $this->assertStringContainsString(e($u->daftarSatkerDipimpin()), $isi);

        /* Pemberitahuan untuk Satker kedua juga sampai. */
        $this->masuk('setba@contoh.test');
        $k = Pemberitahuan::tulis($r, 'Status SIPTL dicatat', [P::PIMPINAN], null, [$dua[1]]);
        $this->assertTrue(Pemberitahuan::boleh($k, $u));
    }

    public function test_pimpinan_semua_satker_melihat_seluruhnya_tetapi_hanya_membaca(): void
    {
        $u = $this->pimpinan();
        $u->forceFill(['pimpinan_satker_ids' => null, 'pimpinan_semua' => true])->save();
        $u = $u->fresh();

        $this->assertSame(Rekomendasi::whereHas('sasaran')->count(), Terlihat::untuk($u)->rekomendasi()->count());
        $index = $this->actingAs($u)->get(route('rekomendasi.index', ['keadaan' => 'semua']))->assertOk();
        $this->assertCount(Satker::count(), $index->viewData('daftarSatker'));
        $index->assertSee('Semua Satker');
        $s = Sasaran::firstOrFail();
        $this->post(route('tanggapan.simpan', $s), ['aksi' => 'draf', 'uraian' => 'coba'])->assertForbidden();
        $this->get(route('master'))->assertForbidden();
    }

    public function test_admin_mengatur_beberapa_satker_atau_semua_satker(): void
    {
        $u = $this->pimpinan();
        $a = $this->akun('bandung')->satker;
        $b = $this->akun('medan')->satker;
        $this->masuk('admin@contoh.test')->post(route('master.pengguna.simpan', $u),
            ['perans' => ['pimpinan'], 'pimpinan_satker' => [$a->id, $b->id]])->assertSessionHas('pesan');
        $harap = [$a->id, $b->id];
        sort($harap);
        $this->assertSame($harap, $u->fresh()->satkerDipimpin());
        $log = LogAktivitas::where('aksi', 'master.pengguna.peran')->latest('id')->firstOrFail();
        $this->assertSame(Satker::whereKey($harap)->orderBy('id')->get()->map->namaPendek()->join(', '), $log->rincian['sesudah']['satker_pimpinan']);

        $this->post(route('master.pengguna.simpan', $u), ['perans' => ['pimpinan'], 'pimpinan_semua' => '1'])->assertSessionHas('pesan');
        $this->assertTrue($u->fresh()->pimpinan_semua);
        $this->assertSame('Semua Satker', LogAktivitas::where('aksi', 'master.pengguna.peran')->latest('id')->firstOrFail()->rincian['sesudah']['satker_pimpinan']);
        $this->assertSame(Satker::count(), count($u->fresh()->satkerDipimpin()));

        /* Isiannya: daftar centang dan Semua Satker. */
        $this->get(route('master', ['tab' => 'pengguna', 'jendela' => 'akun-'.$u->id]))->assertOk()
            ->assertSee('name="pimpinan_satker[]"', false)->assertSee('name="pimpinan_semua"', false)->assertSee('Semua Satker');
    }

    public function test_perintah_server_beberapa_satker_dan_semua_satker(): void
    {
        $p = DirektoriIrm::semua()->firstWhere('nama', 'Dewi Kartikasari');
        $dua = [$this->akun('medan')->satker_id, $this->akun('bandung')->satker_id];
        sort($dua);
        $this->assertSame(0, Artisan::call('simtlhp:pengguna', ['nip' => $p['nip'], 'peran' => 'pimpinan', '--satker' => implode(',', $dua)]));
        $this->assertSame($dua, User::where('nip', $p['nip'])->firstOrFail()->satkerDipimpin());
        $this->assertSame(1, Artisan::call('simtlhp:pengguna', ['nip' => $p['nip'], 'peran' => 'pimpinan', '--satker' => $dua[0].',999999']));
        $this->assertSame(0, Artisan::call('simtlhp:pengguna', ['nip' => $p['nip'], 'peran' => 'pimpinan', '--satker' => 'semua']));
        $this->assertTrue(User::where('nip', $p['nip'])->firstOrFail()->pimpinan_semua);
    }
}
