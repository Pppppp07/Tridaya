<?php

namespace Tests\Feature;

use App\Enums\PosisiBerkas;
use App\Models\LogAktivitas;
use App\Models\RiwayatBerkas;
use App\Models\Sasaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/** Penolakan nyata dari middleware dan pengendali, tanpa mengubah data hidup. */
class PenolakanLogTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
    }

    public function test_abort_dari_middleware_hanya_melihat_tercatat_sekali_tanpa_menyimpan_isian(): void
    {
        $u = $this->akun('dti@contoh.test');
        $sebelum = LogAktivitas::max('id');
        $riwayat = RiwayatBerkas::count();
        $sasaran = Sasaran::firstOrFail();

        $this->actingAs($u)->post(route('sasaran.teruskan', $sasaran), [
            'nomor' => 'Bukan surat sah',
            'password' => 'rahasia-yang-tidak-boleh-tercatat',
            'token' => 'token-isian-pribadi',
        ], ['User-Agent' => 'UjiPenolakan/1.0'])->assertForbidden();

        $baru = LogAktivitas::where('id', '>', $sebelum)->get();
        $this->assertCount(1, $baru);
        $log = $baru->first();
        $this->assertSame('akses.ditolak', $log->aksi);
        $this->assertSame($u->id, $log->user_id);
        $this->assertSame('dti', $log->peran);
        $this->assertSame('POST', $log->rincian['metode']);
        $this->assertSame(403, $log->rincian['code_http']);
        $this->assertSame('/sasaran/'.$sasaran->id.'/teruskan', $log->rincian['alamat']);
        $this->assertSame('sasaran.teruskan', $log->rincian['rute']);
        $this->assertSame('UjiPenolakan/1.0', $log->agen);
        $this->assertStringNotContainsString('rahasia-yang', json_encode($log->toArray()));
        $this->assertStringNotContainsString('token-isian-pribadi', json_encode($log->toArray()));
        $this->assertSame($riwayat, RiwayatBerkas::count());
    }

    public function test_penolakan_scope_pengendali_menunjuk_objek_tujuan_dan_tidak_mengubah_berkas(): void
    {
        $u = $this->akun('medan');
        $sasaran = Sasaran::with('tindakan.rekomendasi')->where('satker_id', '!=', $u->satker_id)->firstOrFail();
        $sebelum = LogAktivitas::max('id');
        $riwayat = RiwayatBerkas::count();
        $posisi = $sasaran->pos();

        $this->actingAs($u)->post(route('tanggapan.simpan', $sasaran), [
            'aksi' => 'draf', 'uraian' => 'Isian pada satuan kerja yang berbeda.',
        ])->assertForbidden();

        $baru = LogAktivitas::where('id', '>', $sebelum)->get();
        $this->assertCount(1, $baru);
        $log = $baru->first();
        $this->assertSame('akses.ditolak', $log->aksi);
        $this->assertSame($u->satker_id, $log->satker_id);
        $this->assertSame('rekomendasi', $log->subjek_tipe);
        $this->assertSame($sasaran->tindakan->rekomendasi_id, $log->subjek_id);
        $this->assertSame('tanggapan.simpan', $log->rincian['rute']);
        $this->assertSame($riwayat, RiwayatBerkas::count());
        $this->assertSame($posisi, $sasaran->fresh()->pos());
    }

    public function test_penolakan_get_mencatat_alamat_tanpa_query_dan_tidak_memercayai_proxy_sembarang(): void
    {
        config(['simtlhp.keamanan.proxy_tepercaya' => '']);
        $u = $this->akun('setba@contoh.test');
        $sebelum = LogAktivitas::max('id');

        $this->actingAs($u)->get(route('master').'?token=rahasia-query', [
            'X-Forwarded-For' => '198.51.100.23',
        ])->assertForbidden();

        $baru = LogAktivitas::where('id', '>', $sebelum)->get();
        $this->assertCount(1, $baru);
        $log = $baru->first();
        $this->assertSame('GET', $log->rincian['metode']);
        $this->assertSame('/data-master', $log->rincian['alamat']);
        $this->assertSame('127.0.0.1', $log->ip);
        $this->assertStringNotContainsString('rahasia-query', json_encode($log->toArray()));
    }

    public function test_baca_yang_diizinkan_dan_validasi_form_biasa_tidak_dicap_penolakan_akses(): void
    {
        $u = $this->akun('medan');
        $sasaran = Sasaran::where('satker_id', $u->satker_id)->get()
            ->first(fn ($s) => $s->pos() === PosisiBerkas::SATKER);
        $this->assertNotNull($sasaran);
        $sebelum = LogAktivitas::max('id');

        $this->actingAs($u)->get(route('rekomendasi.index'))->assertOk();
        $this->post(route('tanggapan.simpan', $sasaran), ['aksi' => 'draf'])
            ->assertRedirect()->assertSessionHasErrors('uraian');

        $this->assertSame(0, LogAktivitas::where('id', '>', $sebelum)->count());
    }

    public function test_pembatasan_rute_masuk_mencatat_tamu_tanpa_menyalin_email_password_atau_token(): void
    {
        /* Pengiriman ditahan oleh middleware route sebelum pengendali login. */
        config(['simtlhp.masuk.per_menit_ip' => 30]);
        for ($i = 0; $i < 30; $i++) {
            $this->post('/masuk', [])->assertSessionHasErrors('email');
        }
        $sebelum = LogAktivitas::max('id');
        // Tamu diberi pesan di halaman masuk, tetapi tercatat sebagai 429.
        $this->post('/masuk', [
            'email' => 'dari-isian-pribadi@contoh.test',
            'password' => 'password-pribadi',
            'token' => 'token-pribadi',
        ])->assertRedirect(route('masuk'))->assertSessionHasErrors('email');

        $baru = LogAktivitas::where('id', '>', $sebelum)->get();
        $this->assertCount(1, $baru);
        $log = $baru->first();
        $this->assertSame('akses.dibatasi', $log->aksi);
        $this->assertNull($log->user_id);
        $this->assertNull($log->nama);
        $this->assertSame(429, $log->rincian['code_http']);
        $this->assertSame('/masuk', $log->rincian['alamat']);
        $this->assertSame('POST', $log->rincian['metode']);
        $this->assertStringNotContainsString('pribadi', json_encode($log->toArray()));

        // Skrip tetap mendapat 429 apa adanya, juga tercatat.
        $this->postJson('/masuk', [])->assertStatus(429);
        $this->assertSame(2, LogAktivitas::where('id', '>', $sebelum)->where('aksi', 'akses.dibatasi')->count());
    }

    /** Pencatatan 429 diuji lewat rute uji (tidak ada lagi rute berlogin yang dibatasi). */
    public function test_pembatasan_rute_berlogin_mencatat_pelakunya_tanpa_isian(): void
    {
        Route::middleware(['web', 'auth', 'throttle:6,1'])
            ->post('/uji/batas-berlogin', fn () => response('ok'))->name('uji.batas-berlogin');
        $u = $this->akun('setba@contoh.test');
        $this->actingAs($u);
        for ($i = 0; $i < 6; $i++) {
            $this->post('/uji/batas-berlogin')->assertOk();
        }
        $sebelum = LogAktivitas::max('id');
        // User diberi pesan di halamannya, tetapi tercatat sebagai 429.
        $this->from(route('rekomendasi.index'))->post('/uji/batas-berlogin', ['catatan' => 'isian-pribadi'])
            ->assertRedirect(route('rekomendasi.index'))->assertSessionHas('gagal');

        $baru = LogAktivitas::where('id', '>', $sebelum)->get();
        $this->assertCount(1, $baru);
        $log = $baru->first();
        $this->assertSame('akses.dibatasi', $log->aksi);
        $this->assertSame($u->id, $log->user_id);
        $this->assertSame(429, $log->rincian['code_http']);
        $this->assertSame('uji.batas-berlogin', $log->rincian['rute']);
        $this->assertStringNotContainsString('isian-pribadi', json_encode($log->toArray()));
    }

    public function test_akun_nonaktif_yang_masih_punya_sesi_dikeluarkan_dan_tercatat_sekali(): void
    {
        $u = $this->akun('medan');
        $u->forceFill(['aktif' => false])->save();
        $sebelum = LogAktivitas::max('id');

        $this->actingAs($u)->get(route('rekomendasi.index'))->assertRedirect(route('masuk'));
        $this->assertGuest();
        $baru = LogAktivitas::where('id', '>', $sebelum)->get();
        $this->assertCount(1, $baru);
        $log = $baru->first();
        $this->assertSame('akses.akun_nonaktif', $log->aksi);
        $this->assertSame($u->id, $log->user_id);
        $this->assertSame($u->id, $log->subjek_id);
        $this->assertSame('user', $log->subjek_tipe);
        $this->assertSame('izin masuk nonaktif', $log->rincian['alasan']);
        $this->get(route('masuk'))->assertOk();
        $this->assertCount(1, LogAktivitas::where('id', '>', $sebelum)->get());
    }
}
