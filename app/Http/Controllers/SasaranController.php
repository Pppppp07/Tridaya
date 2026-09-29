<?php

namespace App\Http\Controllers;

use App\Aksi\KirimUlang;
use App\Aksi\PutusPeriksa;
use App\Aksi\Teruskan;
use App\Enums\HasilTelaah;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Models\Sasaran;
use App\Rules\LinkAman;
use Illuminate\Http\Request;

/**
 * Gerak berkas satu baris penugasan oleh pemeriksanya: Setba meneruskan dan
 * mengirim ulang, UKI dan Inspektorat memutus. Siapa boleh apa bergantung pada
 * posisi baris itu sendiri — dua baris pada rekomendasi yang sama bisa berada
 * di dua meja berbeda.
 */
class SasaranController extends Controller
{
    public function teruskan(Request $req, Sasaran $sasaran)
    {
        abort_unless(auth()->user()->peran === PeranPengguna::SETBA, 403);
        abort_unless(in_array($sasaran->pos(), [PosisiBerkas::SETBA_TINJAU, PosisiBerkas::SETBA_TERUSKAN], true), 422,
            'Berkas tidak sedang menunggu diteruskan Setba.');

        /* Nomor, tanggal, dan perihal wajib — UKI menolak berkas yang datang
           tanpa surat dari Setba. Link dan catatan boleh menyusul. */
        $surat = $req->validate([
            'nomor'   => ['required', 'string', 'max:120'],
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'perihal' => ['required', 'string', 'max:500'],
            'link'  => ['nullable', 'string', 'max:500', new LinkAman],
            'catatan' => ['nullable', 'string', 'max:500'],
        ], [
            'nomor.required' => 'Nomor, tanggal, dan perihal surat harus terisi.',
            'tanggal.required' => 'Nomor, tanggal, dan perihal surat harus terisi.',
            'perihal.required' => 'Nomor, tanggal, dan perihal surat harus terisi.',
        ]);

        Teruskan::jalankan($sasaran->tindakan->rekomendasi, $sasaran->load('satker', 'tindakan'), $surat + ['link' => null, 'catatan' => null]);

        return redirect()->route('rekomendasi.index', ['tuju' => $sasaran->tindakan->rekomendasi_id]);
    }

    public function putus(Request $req, Sasaran $sasaran)
    {
        $peran = auth()->user()->peran;
        $pos = $sasaran->pos();
        /* Meja Inspektorat boleh dikerjakan Setba — ia menyalin isi surat CHV. */
        $boleh = ($pos === PosisiBerkas::UKI && $peran === PeranPengguna::UKI)
            || ($pos === PosisiBerkas::INSPEKTORAT && in_array($peran, [PeranPengguna::INSPEKTORAT, PeranPengguna::SETBA], true));
        abort_unless($boleh, 403, 'Berkas tidak sedang di meja Anda.');

        $uki = $pos === PosisiBerkas::UKI;
        $hasil = HasilTelaah::tryFrom((string) $req->input('hasil'));
        abort_unless($hasil, 422, 'Pilih putusannya lebih dulu.');

        /* Memadai berdiri di atas surat: nomor dan tanggal wajib. Belum memadai
           justru catatannya yang wajib, dan penolakan Inspektorat membawa batas
           waktu perbaikan. */
        $data = $req->validate([
            'nomor'         => [$hasil === HasilTelaah::M ? 'required' : 'nullable', 'string', 'max:120'],
            'tgl_surat'     => [$hasil === HasilTelaah::M ? 'required' : 'nullable', 'date', 'before_or_equal:today'],
            'catatan'       => [$hasil === HasilTelaah::BM ? 'required' : 'nullable', 'string', $hasil === HasilTelaah::BM ? 'min:6' : 'min:0', 'max:5000'],
            'batas_waktu'   => [! $uki && $hasil === HasilTelaah::BM ? 'required' : 'nullable', 'date', 'after_or_equal:today'],
            'perihal'       => ['nullable', 'string', 'max:500'],
            'berkas'        => ['nullable', 'string', 'max:255'],
            'link'        => ['nullable', 'string', 'max:500', new LinkAman],
            'nomor_lhv'     => ['nullable', 'string', 'max:120'],
            'tgl_lhv'       => ['nullable', 'date', 'before_or_equal:today'],
            'dokumen'       => ['array'],
            'dokumen.*'     => ['nullable', 'string', 'max:255'],
            'tanda_hasil'   => ['nullable', 'in:M,BM'],
            'tanda_catatan' => ['nullable', 'string', 'max:1000'],
        ], [
            'nomor.required'       => 'Nomor dan tanggal surat harus terisi — putusan memadai berdiri di atas suratnya.',
            'tgl_surat.required'   => 'Nomor dan tanggal surat harus terisi — putusan memadai berdiri di atas suratnya.',
            'catatan.required'     => 'Catatan harus diisi supaya satuan kerja tahu apa yang perlu diperbaiki.',
            'catatan.min'          => 'Catatan harus diisi supaya satuan kerja tahu apa yang perlu diperbaiki.',
            'batas_waktu.required' => 'Batas waktu perbaikan harus diisi, dan tidak boleh sebelum hari ini.',
        ]);

        $rek = $sasaran->tindakan->rekomendasi;
        $rek->load('temuan.laporan');

        PutusPeriksa::jalankan($rek, $sasaran->load('satker', 'tindakan'), $peran, $hasil, [
            'catatan'        => $data['catatan'] ?? '',
            'nomor'          => $data['nomor'] ?? '',
            'tglSurat'       => $data['tgl_surat'] ?? '',
            'perihal'        => $data['perihal'] ?? '',
            'berkas'         => $data['berkas'] ?? '',
            'link'         => $data['link'] ?? '',
            'batasWaktu'     => ! $uki && $hasil === HasilTelaah::BM ? ($data['batas_waktu'] ?? '') : '',
            'dokumenDiminta' => $hasil === HasilTelaah::BM ? ($data['dokumen'] ?? []) : [],
            'nomorLhv'       => ! $uki ? ($data['nomor_lhv'] ?? '') : '',
            'tglLhv'         => ! $uki ? ($data['tgl_lhv'] ?? '') : '',
            /* Tanda yang dikosongkan ikut putusan akhir. */
            'tanda'          => [['satker_id' => $sasaran->satker_id,
                'hasil' => $data['tanda_hasil'] ?? $hasil->value,
                'catatan' => $data['tanda_catatan'] ?? '']],
        ]);

        return redirect()->route('rekomendasi.index', ['tuju' => $sasaran->tindakan->rekomendasi_id]);
    }

    public function kirimUlang(Request $req, Sasaran $sasaran)
    {
        abort_unless(auth()->user()->peran === PeranPengguna::SETBA, 403);
        abort_unless($sasaran->pos() === PosisiBerkas::SETBA_KEMBALI, 422,
            'Berkas tidak sedang di meja pemberkasan ulang.');

        $data = $req->validate([
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'dokumen'    => ['array'],
            'dokumen.*'  => ['nullable', 'string', 'max:255'],
        ]);

        KirimUlang::jalankan($sasaran->tindakan->rekomendasi, $sasaran->load('satker', 'tindakan'),
            $data['keterangan'] ?? null, $data['dokumen'] ?? []);

        return redirect()->route('rekomendasi.index', ['tuju' => $sasaran->tindakan->rekomendasi_id]);
    }
}
