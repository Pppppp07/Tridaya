<?php

namespace Tests\Feature;

use App\Enums\PosisiBerkas;
use App\Models\RiwayatStatus;
use App\Models\Sasaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\PakaiDataContoh;
use Tests\TestCase;

/** Nama orang dan jam tiap langkah tindak lanjut (satuan kerja, Setba, UKI). */
class PelakuRiwayatTest extends TestCase
{
    use PakaiDataContoh, RefreshDatabase;

    private Sasaran $baris;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanDataContoh();
        $this->baris = Sasaran::with('tindakan.rekomendasi.temuan.laporan', 'satker')->get()
            ->first(fn ($x) => $x->pos() === PosisiBerkas::SATKER
                && $x->tindakan->rekomendasi->jenis()->melewatiSiptl());
        $this->assertNotNull($this->baris, 'Data contoh perlu memuat baris yang masih di satuan kerja.');
    }

    /**
     * Satu baris tabel Progres: kolom Tanggal memuat jamnya (24 jam, WIB), dan
     * baris pelakunya "Oleh <nama> (<peran>)" — di baris tabel yang sama.
     */
    private function pola(User $u, string $peran, string $jam): string
    {
        return '/<time datetime="2026-08-17T'.str_replace('.', ':', $jam).'">17 Agu 2026<\/time>\s*'
            .'<span class="rw-pukul">'.preg_quote($jam, '/').' WIB<\/span>(?:(?!<\/tr>).)*?'
            .'rw-pelaku">'.preg_quote(e($u->name), '/').'<\/b>\s*\('.preg_quote(e($peran), '/').'\)<\/span>/su';
    }

    public function test_tiap_langkah_menyebut_nama_orang_dan_jamnya(): void
    {
        $baris = $this->baris;
        $rek = $baris->tindakan->rekomendasi;
        $pj = User::where('satker_id', $baris->satker_id)->where('aktif', true)->firstOrFail();
        $setba = $this->akun('setba@contoh.test');
        $uki = $this->akun('uki@contoh.test');

        /* 1. Satuan kerja mengirim tindak lanjutnya, pukul 09.14. */
        Carbon::setTestNow('2026-08-17 09:14:00');
        $rek->load('permintaanDokumen.item');
        $bukti = $rek->permintaanUntuk($baris->satker_id, $baris->tindakan_id)
            ->flatMap->item->where('terpenuhi', false)
            ->map(fn ($i) => ['nama' => 'Bukti '.$i->nama, 'link' => 'https://contoh.test/'.$i->id,
                'jenis' => $i->nama, 'untuk' => $i->id])
            ->values()->all();
        $this->actingAs($pj)->post(route('tanggapan.simpan', $baris), [
            'aksi' => 'kirim', 'uraian' => 'Tindak lanjut sudah dilaksanakan sesuai bunyi rekomendasi.', 'bukti' => $bukti,
        ])->assertRedirect();

        /* 2. Setba meneruskannya ke UKI, pukul 10.05. */
        Carbon::setTestNow('2026-08-17 10:05:00');
        $this->actingAs($setba)->post(route('sasaran.teruskan', $baris->fresh()), [
            'nomor' => 'PW.02.02-Sb/9101', 'tanggal' => '2026-08-17', 'perihal' => 'Penyampaian tindak lanjut untuk ditelaah',
        ])->assertRedirect();

        /* 3. UKI menilai memadai, pukul 13.40. */
        Carbon::setTestNow('2026-08-17 13:40:00');
        $this->actingAs($uki)->post(route('sasaran.putus', $baris->fresh()), [
            'hasil' => 'M', 'nomor' => '101/VAL-UKI/BPSDM/VIII/2026', 'tgl_surat' => '2026-08-17',
            'catatan' => 'Bukti lengkap dan sesuai rekomendasi.',
        ])->assertRedirect();

        /* Perubahan status kini menyimpan akun yang mencatatnya. */
        $this->assertSame($uki->id, RiwayatStatus::where('sasaran_id', $baris->id)->latest('id')->firstOrFail()->oleh_id);

        $isi = $this->actingAs($setba)->get(route('rekomendasi.show', $rek))->assertOk()->getContent();
        $this->assertMatchesRegularExpression($this->pola($pj, $baris->satker->namaPendek(), '09.14'), $isi);
        $this->assertMatchesRegularExpression($this->pola($setba, 'Setba', '10.05'), $isi);
        $this->assertMatchesRegularExpression($this->pola($uki, 'UKI', '13.40'), $isi);

        /* Tab Bukti: baris "Dikirim oleh" sendiri, di atas tanggapannya —
           nama pengirim dan jam kirimnya; tanggapannya tanpa nama. */
        $this->assertMatchesRegularExpression('/>Dikirim oleh<.*?<b class="rw-pelaku">'.preg_quote(e($pj->name), '/')
            .'<\/b><span class="kerja-meta">17 Agu 2026, 09\.14<\/span>.*?>Tanggapan satuan kerja</su', $isi);
        $this->assertStringContainsString('Tindak lanjut sudah dilaksanakan sesuai bunyi rekomendasi.', $isi);
        $this->assertDoesNotMatchRegularExpression('/bunyi rekomendasi\.<span class="kerja-meta"><b class="rw-pelaku">/u', $isi);

        /* Kartu Bukti hanya menyebut pengirim satuan kerja; nama Setba, UKI, dan Inspektorat
           cukup di tab Progres. */
        $this->assertStringNotContainsString('Diteruskan ke UKI oleh', $isi);
        $this->assertStringNotContainsString('Divalidasi oleh', $isi);
        $this->assertStringNotContainsString('Diverifikasi oleh', $isi);
    }

    /**
     * Riwayat data contoh hanya menyimpan sebutan peran dan tanggal. Nama
     * orangnya dilengkapi dari sebutan itu; jamnya tidak dikarang.
     */
    public function test_riwayat_contoh_menyebut_nama_tanpa_jam_karangan(): void
    {
        $this->assertSame(0, RiwayatStatus::whereNull('oleh_id')->count());
        $this->assertSame(0, RiwayatStatus::whereNotNull('created_at')->count());
        $setba = $this->akun('setba@contoh.test');
        $this->assertSame(RiwayatStatus::where('oleh', 'Setba')->count(),
            RiwayatStatus::where('oleh_id', $setba->id)->count());

        $rek = RiwayatStatus::where('oleh', 'Setba')->firstOrFail()->sasaran->tindakan->rekomendasi;
        $isi = $this->actingAs($setba)->get(route('rekomendasi.show', $rek))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/rw-pelaku">'.preg_quote(e($setba->name), '/').'<\/b>\s*\(Setba\)<\/span>/u', $isi);
        /* Riwayat contoh lama tidak punya jam: kolom Tanggal hanya bertanggal. */
        $this->assertStringNotContainsString('rw-pukul', $isi);
        $this->assertStringNotContainsString('rw-dicatat', $isi);
    }
}
