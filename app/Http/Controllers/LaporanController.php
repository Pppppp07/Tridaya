<?php

namespace App\Http\Controllers;

use App\Models\Laporan;
use App\Support\Lingkup;
use App\Support\Terlihat;
use Illuminate\Http\Request;

/**
 * Daftar laporan dan Rincian laporan — padanan `DaftarLaporan` dan
 * `RincianLaporan`. Lingkup (LHP / LHA) yang dipilih di layar Rekomendasi
 * ikut berlaku di sini.
 */
class LaporanController extends Controller
{
    public function index(Request $req)
    {
        $lingkup = Lingkup::dari($req);
        /* Difilter di hulu, bukan di tampilan. */
        $semua = Terlihat::untuk()->daftarLaporan()
            ->filter(fn ($l) => Lingkup::berlaku($lingkup, $l->sumber))
            ->each(fn ($l) => $l->angkaTersimpan = $l->angka())
            ->values();

        $KEADAAN = [
            'jalan'  => ['nama' => 'Masih berjalan', 'uji' => fn ($l) => ! $l->angkaTersimpan['beres']],
            'tuntas' => ['nama' => 'Sudah tuntas', 'uji' => fn ($l) => $l->angkaTersimpan['beres']],
            'semua'  => ['nama' => 'Semua', 'uji' => fn () => true],
        ];
        /* Angkanya dari daftar penuh, bukan dari hasil pencarian. */
        $jumlah = array_map(fn ($k) => $semua->filter($k['uji'])->count(), $KEADAAN);

        $keadaan = $req->query('keadaan', 'jalan');
        $keadaan = isset($KEADAAN[$keadaan]) ? $keadaan : 'jalan';
        $cari = trim((string) $req->query('cari', ''));
        [$urut, $arah] = array_pad(explode(':', (string) $req->query('urut', 'perhatian')), 2, 'naik');
        if (! in_array($urut, ['perhatian', 'nomor', 'diterima', 'temuan', 'rekomendasi', 'dana', 'satker'], true)) {
            $urut = 'perhatian';
        }

        $cocok = fn ($l) => $cari === '' || str_contains(mb_strtolower(implode(' ', [
            $l->nomor, $l->satkerDiperiksa()->map->nama->join(' '), $l->sumber->nama(), $l->temuan->map->judul->join(' '),
        ])), mb_strtolower($cari));

        $hasil = $semua->filter($cocok)->filter($KEADAAN[$keadaan]['uji'])
            ->sort(function ($x, $y) use ($urut, $arah) {
                $a = $x->angkaTersimpan;
                $b = $y->angkaTersimpan;
                $perhatian = $b['perlu'] <=> $a['perlu'];
                if ($urut === 'perhatian') {
                    return $perhatian;
                }
                $banding = match ($urut) {
                    'nomor'       => strcmp($x->nomor, $y->nomor),
                    'diterima'    => strcmp((string) $x->tgl_terima?->toDateString(), (string) $y->tgl_terima?->toDateString()),
                    'satker'      => strcmp((string) $x->satkerDiperiksa()->first()?->nama, (string) $y->satkerDiperiksa()->first()?->nama)
                                     ?: $x->satkerDiperiksa()->count() <=> $y->satkerDiperiksa()->count(),
                    'temuan'      => $a['temuan'] <=> $b['temuan'],
                    'rekomendasi' => $a['jml'] <=> $b['jml'],
                    default       => $a['masuk'] <=> $b['masuk'],
                };

                return ($arah === 'turun' ? -$banding : $banding) ?: $perhatian;
            })
            ->values();

        return view('laporan.index', [
            'hasil'   => $hasil,
            'semua'   => $semua,
            'KEADAAN' => $KEADAAN,
            'jumlah'  => $jumlah,
            'keadaan' => $keadaan,
            'cari'    => $cari,
            'urut'    => $urut,
            'arah'    => $arah,
            'perlu'   => $hasil->sum(fn ($l) => $l->angkaTersimpan['perlu']),
        ]);
    }

    public function show(Laporan $laporan)
    {
        $laporan->load([
            'temuan.satkers', 'temuan.kategori', 'temuan.kategoriIntern', 'temuan.laporan',
            'temuan.rekomendasi.sasaran.satker', 'temuan.rekomendasi.tindakan.bentuk',
            'temuan.rekomendasi.pemulihan', 'temuan.rekomendasi.tolakanBpk',
            'temuan.rekomendasi.permintaanDokumen.item', 'temuan.rekomendasi.riwayat', 'lampiran',
        ]);
        $laporan->temuan->each(fn ($t) => $t->rekomendasi->each(fn ($r) => $r->setRelation('temuan', $t)));

        $terlihat = Terlihat::untuk();

        /* Laporan yang sama sekali tidak menyangkut pengguna ini tidak dibuka —
           halaman kosong tetap membocorkan bahwa laporannya ada. */
        abort_unless($terlihat->bolehLihatLaporan($laporan), 403,
            'Laporan ini tidak menyangkut satuan kerja Anda.');

        return view('laporan.show', ['lap' => $terlihat->pangkas($laporan)]);
    }
}
