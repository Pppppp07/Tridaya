<?php

namespace App\Http\Controllers;

use App\Aksi\Kemajuan;
use App\Aksi\SimpanTanggapan;
use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Models\Sasaran;
use App\Rules\LinkAman;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Satuan kerja mengisi tindak lanjut satu baris: simpan draf, atau kirim ke
 * Setba. Syaratnya sama persis dengan tombol di PanelBalai prototipe —
 * diperiksa ulang di sini, karena tombol yang mati di browser bukan penjaga.
 */
class TanggapanController extends Controller
{
    public function simpan(Request $req, Sasaran $sasaran)
    {
        $u = auth()->user();
        abort_unless($u->peran === PeranPengguna::SATKER && $sasaran->satker_id === $u->satker_id, 403,
            'Baris ini bukan milik satuan kerja Anda.');
        abort_unless($sasaran->pos() === PosisiBerkas::SATKER, 422,
            'Berkas ini sudah tidak di meja satuan kerja.');

        $data = $req->validate([
            'aksi'              => ['required', 'in:draf,kirim'],
            'uraian'            => ['required', 'string', 'max:5000'],
            'tanggal'           => ['nullable', 'date', 'before_or_equal:today'],
            'bukti'             => ['array'],
            'bukti.*.nama'      => ['nullable', 'string', 'max:255'],
            'bukti.*.link'    => ['nullable', 'string', 'max:500', new LinkAman],
            'bukti.*.jenis'     => ['nullable', 'string', 'max:255'],
            'bukti.*.untuk'     => ['nullable', 'integer'],
            'setoran'           => ['array'],
            'setoran.*.jenis'   => ['nullable', 'in:setor,perbaikan'],
            'setoran.*.nilai'   => ['nullable', 'string', 'max:30'],
            'setoran.*.tanggal' => ['nullable', 'date'],
            'setoran.*.ssbp'    => ['nullable', 'string', 'max:100'],
            'setoran.*.ntpn'    => ['nullable', 'string', 'max:32'],
            'setoran.*.notaKppn'=> ['nullable', 'string', 'max:100'],
            'setoran.*.noBa'    => ['nullable', 'string', 'max:100'],
            'setoran.*.berkas'  => ['nullable', 'string', 'max:255'],
            'setoran.*.link'  => ['nullable', 'string', 'max:500', new LinkAman],
        ], [
            'uraian.required' => 'Uraian tindak lanjut harus terisi.',
        ]);

        $rek = $sasaran->tindakan->rekomendasi;
        $rek->load(['permintaanDokumen.item', 'pemulihan', 'tolakanBpk', 'sasaran', 'tindakan', 'temuan.laporan']);
        $sasaran->load('satker', 'draf', 'tindakan');

        $bukti = collect($data['bukti'] ?? [])->map(fn ($b) => [
            'nama' => trim((string) ($b['nama'] ?? '')), 'link' => trim((string) ($b['link'] ?? '')),
            'jenis' => (string) ($b['jenis'] ?? ''), 'untuk' => $b['untuk'] ?? null,
        ])->values()->all();
        $setoran = collect($data['setoran'] ?? [])->map(fn ($s) => array_map(fn ($v) => is_string($v) ? trim($v) : $v, $s + [
            'jenis' => 'setor', 'nilai' => '', 'tanggal' => '', 'ssbp' => '', 'ntpn' => '', 'notaKppn' => '', 'noBa' => '', 'berkas' => '', 'link' => '',
        ]))->values()->all();

        /* Butir dianggap terpenuhi begitu link-nya lengkap — tidak ada centang
           terpisah yang bisa berbeda dari kenyataan berkasnya. Dihitung di
           sini, bukan dipercaya dari browser. */
        $belum = $rek->permintaanUntuk($sasaran->satker_id, $sasaran->tindakan_id)->flatMap->item->where('terpenuhi', false);
        $sah = fn ($b) => $b['nama'] !== '' && $b['link'] !== '';
        $penuhi = $belum->filter(fn ($i) => collect($bukti)->contains(fn ($b) => (int) $b['untuk'] === $i->id && $sah($b)))
            ->pluck('id')->all();

        $dana = $rek->progresDana($sasaran->satker_id, $sasaran->tindakan_id);
        $bakal = $dana ? $dana['masuk'] + collect($setoran)->filter(fn ($x) => Kemajuan::setorSah($x))->sum(fn ($x) => Kemajuan::angka($x['nilai'])) : 0;
        if ($dana && $bakal > $dana['target']) {
            throw ValidationException::withMessages(['setoran' => 'Nilai pemulihan melebihi kewajiban — periksa angkanya dulu.']);
        }

        $isi = ['uraian' => $data['uraian'], 'tanggal' => $data['tanggal'] ?? null,
            'bukti' => $bukti, 'setoran' => $setoran, 'penuhi' => $penuhi];

        if ($data['aksi'] === 'draf') {
            SimpanTanggapan::draf($rek, $sasaran, $isi);

            return back();
        }

        $sisaDok = $belum->count() - count($penuhi);
        $buktiKurang = collect($bukti)->reject($sah)->count();
        $setorKurang = collect($setoran)->reject(fn ($x) => Kemajuan::setorSah($x))->count();
        $kurang = match (true) {
            $sisaDok > 0     => "{$sisaDok} dokumen masih kurang, berkas belum bisa dikirim.",
            $buktiKurang > 0 => "{$buktiKurang} link belum lengkap, berkas belum bisa dikirim.",
            $setorKurang > 0 => "{$setorKurang} baris pemulihan belum lengkap — lengkapi atau hapus dulu.",
            default          => null,
        };
        if ($kurang) {
            throw ValidationException::withMessages(['kirim' => $kurang]);
        }

        SimpanTanggapan::kirim($rek, $sasaran, $isi);

        return redirect()->route('rekomendasi.index', ['tuju' => $sasaran->tindakan->rekomendasi_id]);
    }
}
