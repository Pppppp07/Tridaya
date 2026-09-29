<?php

namespace App\Http\Controllers;

use App\Support\Lingkup;
use App\Support\Terlihat;
use App\Support\Tampil;
use Illuminate\Http\Request;

/**
 * Pencarian di batang atas — padanan `CariGlobal`.
 *
 * Yang dicari diambil dari daftar yang hak aksesnya sudah difilter, jadi
 * satuan kerja tetap tidak bisa menemukan berkas satuan kerja lain lewat
 * pintu ini. Rekomendasi lebih dulu (paling banyak enam), laporan menyusul
 * (paling banyak tiga).
 */
class CariController extends Controller
{
    public function __invoke(Request $req)
    {
        $kata = mb_strtolower(trim((string) $req->query('q', '')));
        if (mb_strlen($kata) < 2) {
            return response()->json(['rek' => [], 'lap' => [], 'jumlah' => 0]);
        }

        $terlihat = Terlihat::untuk();
        $lingkup = Lingkup::dari();
        $cocok = fn (?string $t) => str_contains(mb_strtolower((string) $t), $kata);

        $rek = $terlihat->rekomendasi()
            ->with(['temuan.laporan', 'sasaran.satker'])
            ->get()
            ->map(fn ($r) => $terlihat->pangkasRekomendasi($r))
            ->filter(fn ($r) => Lingkup::berlaku($lingkup, $r->jenis()))
            ->filter(fn ($r) => $cocok($r->kode) || $cocok($r->uraian)
                || $cocok($r->daftarSasaran()->first()?->satker?->nama)
                || $cocok($r->temuan->kode) || $cocok($r->temuan->judul) || $cocok($r->temuan->nomor_pada_surat))
            ->values();

        $lap = $terlihat->daftarLaporan()
            ->filter(fn ($l) => Lingkup::berlaku($lingkup, $l->sumber))
            ->filter(fn ($l) => $cocok($l->nomor) || $cocok($l->sumber->nama())
                || $cocok($l->satkerDiperiksa()->map->nama->join(' '))
                || $l->temuan->contains(fn ($t) => $cocok($t->judul) || $cocok($t->kode)))
            ->values();

        return response()->json([
            'rek' => $rek->take(6)->map(fn ($r) => [
                'link'      => route('rekomendasi.show', ['rekomendasi' => $r->id] + self::tujuSatker($r, $cocok)),
                'kode'        => $r->kode,
                /* Satuan kerja membaca keadaan tindak lanjutnya sendiri (27 Sep). */
                'cap'         => view('components.cap', ['s' => $r->status, 'jenis' => $r->jenis(), 'rek' => $r,
                    'satker' => $req->user()->peran === \App\Enums\PeranPengguna::SATKER ? $req->user()->satker_id : null])->render(),
                'uraian'      => $r->uraian,
                'satker'      => $r->daftarSasaran()->first()?->satker?->namaPendek() ?? '—',
                'nomorTemuan' => $r->temuan->nomor_pada_surat,
            ])->values(),
            'lap' => $lap->take(3)->map(fn ($l) => [
                'link'   => route('laporan.show', ['laporan' => $l->id] + self::tujuTemuan($l, $cocok)),
                'nomor'    => $l->nomor,
                'sumber'   => view('components.sumber', ['j' => $l->sumber])->render(),
                'satker'   => view('components.daftar-satker', ['lap' => $l])->render(),
                'temuan'   => $l->temuan->count(),
                'diterima' => Tampil::tgl($l->tgl_terima),
            ])->values(),
            'jumlah' => $rek->count() + $lap->count(),
        ]);
    }

    /**
     * Hasil yang cocok karena nama satuan kerjanya langsung mengantar ke baris
     * satuan kerja itu (26 Sep) — padanan `ambil` di CariGlobal. Yang cocok
     * karena kode atau uraiannya dibuka biasa.
     */
    private static function tujuSatker($r, callable $cocok): array
    {
        if ($cocok($r->kode) || $cocok($r->uraian)) {
            return [];
        }
        $kena = $r->daftarSasaran()->pluck('satker')->filter()->unique('id')
            ->filter(fn ($s) => $cocok($s->nama) || $cocok($s->namaPendek()))->values();

        return $kena->count() === 1 ? ['sorot' => 'r-tindaklanjut', 'satker' => $kena->first()->id] : [];
    }

    /** Laporan yang cocok karena temuannya mengantar ke blok temuan itu. */
    private static function tujuTemuan($l, callable $cocok): array
    {
        if ($cocok($l->nomor)) {
            return [];
        }
        $tem = $l->temuan->first(fn ($t) => $cocok($t->judul) || $cocok($t->kode));

        return $tem ? ['temuan' => $tem->id] : [];
    }
}
