<?php

namespace App\Http\Controllers;

use App\Aksi\UrusanSiptl;
use App\Enums\PeranPengguna;
use App\Enums\StatusTindakLanjut;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use Illuminate\Http\Request;

/** Urusan SIPTL per satuan kerja, milik Setba. Hanya LHP — LHA tidak sampai ke BPK. */
class SiptlController extends Controller
{
    private function jaga(Sasaran $s): Rekomendasi
    {
        abort_unless(auth()->user()->peranAktif() === PeranPengguna::SETBA, 403);
        $rek = $s->tindakan->rekomendasi->load('temuan.laporan');
        abort_unless($rek->jenis()->melewatiSiptl(), 422, 'Laporan ini tidak melewati SIPTL.');
        abort_unless($s->tuntas(), 422, 'Tindak lanjut ini belum selesai diperiksa.');
        $s->load('satker', 'tindakan');

        return $rek;
    }

    /** Tanggal unggah dikunci sekali tercatat; ditolak sebelum menyentuh apa pun. */
    public function unggah(Request $req, Sasaran $sasaran)
    {
        $rek = $this->jaga($sasaran);
        abort_if($sasaran->sudahSiptl(), 422, 'Data SIPTL sudah tercatat dan dikunci.');

        $data = $req->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'tanggal.required' => 'Tanggal unggah harus terisi.',
            'tanggal.before_or_equal' => 'Tanggal unggah tidak boleh melewati hari ini.',
        ]);

        UrusanSiptl::unggah($rek, $sasaran, $data['tanggal']);

        return redirect()->route('rekomendasi.index', ['tuju' => $sasaran->tindakan->rekomendasi_id]);
    }

    /**
     * Status disalin dari SIPTL dan dicatat sekali tiap unggahan, lalu terkunci. Belum Sesuai baru
     * bisa dinilai lagi sesudah kirim ulang (`ulangBpk`). Penjaganya di sini, bukan cuma di layar.
     */
    public function status(Request $req, Sasaran $sasaran)
    {
        $rek = $this->jaga($sasaran);
        abort_unless($sasaran->sudahSiptl(), 422, 'Catat dulu unggahannya ke SIPTL.');
        abort_unless(($sasaran->status_bpk ?? StatusTindakLanjut::BT) === StatusTindakLanjut::BT, 422,
            'Hasil pemantauan BPK untuk unggahan ini sudah dicatat dan tidak bisa diubah lagi.');

        $data = $req->validate([
            'status' => ['required', 'in:BS,SS'],
            'catatan' => [$req->input('status') === 'BS' ? 'required' : 'nullable', 'string', 'max:500'],
            'tgl_pantau' => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'status.required' => 'Pilih hasil pemantauannya.',
            'catatan.required' => 'Catatan BPK harus terisi.',
        ]);

        UrusanSiptl::status($rek, $sasaran, StatusTindakLanjut::from($data['status']), $data['catatan'] ?? null, $data['tgl_pantau'] ?? null);

        return redirect()->route('rekomendasi.index', ['tuju' => $sasaran->tindakan->rekomendasi_id]);
    }

    /** BPK menolak satu tindak lanjut: dikirim ulang ke satuan kerjanya. */
    public function ulangBpk(Request $req, Rekomendasi $rekomendasi)
    {
        /* Nilai boleh bertitik ribuan ("1.500.000"); yang dipakai hanya digitnya. */
        if ($req->filled('nilai')) {
            $req->merge(['nilai' => preg_replace('/\D/', '', (string) $req->input('nilai')) ?: null]);
        }
        $data = $req->validate([
            'sasaran_id' => ['required', 'integer'],
            'alasan' => ['required', 'string', 'max:500'],
            'keterangan' => ['nullable', 'string', 'max:500'],
            'nilai' => ['nullable', 'integer', 'min:0'],
            'dokumen' => ['array'],
            'dokumen.*' => ['nullable', 'string', 'max:255'],
        ], [
            'alasan.required' => 'Catatan untuk satuan kerja harus terisi.',
        ]);

        $sasaran = $rekomendasi->sasaran()->where('sasarans.id', $data['sasaran_id'])->firstOrFail();
        $rek = $this->jaga($sasaran);
        abort_unless($sasaran->status_bpk === StatusTindakLanjut::BS, 422, 'Hanya tindak lanjut yang dinyatakan Belum Sesuai oleh BPK yang bisa dikirim ulang.');

        UrusanSiptl::ulangBpk($rek, $sasaran, $data['alasan'], $data['keterangan'] ?? null,
            $data['dokumen'] ?? [], (int) ($data['nilai'] ?? 0));

        return redirect()->route('rekomendasi.index', ['tuju' => $sasaran->tindakan->rekomendasi_id]);
    }
}
