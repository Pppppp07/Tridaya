<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Enums\StatusTindakLanjut;
use App\Models\Rekomendasi;
use App\Models\Satker;
use App\Support\Lingkup;
use App\Support\Pemberitahuan;
use App\Support\Setelan;
use App\Support\Terlihat;
use App\Support\TindakLanjutRingkas;
use Illuminate\Http\Request;

/**
 * Layar Rekomendasi dan rinciannya. Angka keranjang dihitung dari daftar penuh, bukan hasil filter.
 */
class RekomendasiController extends Controller
{
    public function beranda()
    {
        /* Beranda menurut peran (Pimpinan → Dashboard, DTI → Log), kecuali dipilih di Profil →
           Tampilan. */
        return redirect()->route(Setelan::ruteBeranda(auth()->user()));
    }

    /** Muatan yang dibutuhkan hitungan daftar: meja, kemajuan, tenggat, urgensi. */
    public const MUAT_DAFTAR = [
        'temuan.laporan', 'sasaran.satker', 'tindakan.bentuk',
        'pemulihan', 'tolakanBpk', 'permintaanDokumen.item', 'riwayat',
    ];

    public function index(Request $req)
    {
        $u = auth()->user();
        $peran = $u->peranAktif();
        $satkerAktif = $u->satkerAktifId();
        $terlihat = Terlihat::untuk();
        $lingkup = Lingkup::dari($req);

        /* Urutan dasar = urutan pencatatan (laporan, temuan, rekomendasi). */
        $utuh = $terlihat->rekomendasi()->with(self::MUAT_DAFTAR)->orderBy('rekomendasis.id')->get()
            ->map(fn ($r) => $terlihat->pangkasRekomendasi($r));

        /* Dihitung sebelum lingkup difilter, supaya tombol LHP/LHA tidak menulis nol. */
        $jumlahJenis = [
            'semua' => $utuh->count(),
            'LHP' => $utuh->filter(fn ($r) => $r->jenis()->value === 'LHP')->count(),
            'LHA' => $utuh->filter(fn ($r) => $r->jenis()->value === 'LHA')->count(),
        ];

        $daftar = $utuh->filter(fn ($r) => Lingkup::berlaku($lingkup, $r->jenis()))->values();

        $KEADAAN = [
            'kerja' => ['nama' => 'Perlu dikerjakan', 'bekerja' => true,
                'uji' => fn ($r) => $r->diMeja($peran, $satkerAktif)],
            'tunggu' => ['nama' => 'Sedang menunggu',
                'uji' => fn ($r) => ! $r->beres() && ! $r->diMeja($peran, $satkerAktif)],
            'selesai' => ['nama' => 'Sudah selesai', 'uji' => fn ($r) => $r->beres()],
            /* Potongan "Perlu saya kerjakan" yang dikendalikan sistem luar — hanya Setba,
               lingkup yang memuat LHP. */
            'siptl' => ['nama' => 'Urusan SIPTL', 'khusus' => PeranPengguna::SETBA, 'jenis' => 'LHP', 'ikon' => 'Landmark',
                'uji' => fn ($r) => $r->kerjaSiptl()],
        ];
        /* Peran yang hanya memantau (DTI, Pimpinan) tidak punya pekerjaan: tanpa "Perlu dikerjakan". */
        $keadaanAda = array_filter($KEADAAN, fn ($v) => (! isset($v['khusus']) || $v['khusus'] === $peran)
            && (! isset($v['jenis']) || $lingkup === 'semua' || $lingkup === $v['jenis'])
            && ! (! empty($v['bekerja']) && $peran->hanyaMelihat()));
        $jumlah = array_map(fn ($v) => $daftar->filter($v['uji'])->count(), $KEADAAN);

        /* Keranjang bawaan menurut peran: yang mengerjakan dibukakan pekerjaannya, yang memantau
           dibukakan semuanya. */
        $keadaan = $req->query('keadaan', $peran->hanyaMelihat() ? 'semua' : 'kerja');
        $keadaanKini = isset($keadaanAda[$keadaan]) ? $keadaan : null;

        /* `tuju`: kembali dari rincian atau sesudah tindakan; barisnya disorot skrip. Bila
           berkasnya sudah pindah keranjang, keranjangnya ikut pindah. */
        $tuju = (int) $req->query('tuju', 0);
        $rekTuju = $tuju ? $daftar->firstWhere('id', $tuju) : null;
        if ($rekTuju && $keadaanKini && ! $KEADAAN[$keadaanKini]['uji']($rekTuju)) {
            $keadaanKini = collect(array_keys($keadaanAda))
                ->first(fn ($k) => ! isset($keadaanAda[$k]['khusus']) && $KEADAAN[$k]['uji']($rekTuju));
        }

        $q = trim((string) $req->query('q', ''));
        $sk = $req->query('sk', 'semua');
        /* Kode status yang tidak dikenal (tautan lama) dibaca "semua". */
        $st = is_string($req->query('st')) && StatusTindakLanjut::tryFrom($req->query('st')) ? $req->query('st') : 'semua';
        $stBerlaku = $lingkup === 'LHA' ? 'semua' : $st;
        /* Urutan bawaan mengikuti Profil → Pemberitahuan: pembaruan terbaru dulu, atau yang mendesak. */
        $sorotBaru = (bool) $u->setelan('pembaruan');
        $urutBawaan = $sorotBaru ? 'terbaru' : 'mendesak';
        [$urut, $arah] = array_pad(explode(':', (string) $req->query('urut', $urutBawaan)), 2, 'naik');
        if (! in_array($urut, ['mendesak', 'terbaru', 'keterangan', 'uraian', 'satker', 'tenggat', 'kemajuan'], true)) {
            $urut = $urutBawaan;
        }

        $kata = mb_strtolower($q);
        $cocokTeks = function (Rekomendasi $r) use ($kata) {
            if ($kata === '') {
                return true;
            }
            $t = $r->temuan;
            $teks = implode(' ', [$r->kode, $t->judul, $r->uraian, $r->refIdt(),
                $t->laporan->nomor, $r->refLhp(), $r->asalData(), $r->daftarSasaran()->pluck('satker')->filter()->unique('id')
                    ->map(fn ($s) => $s->nama.' '.$s->namaPendek())->join(' ')]);

            return str_contains(mb_strtolower($teks), $kata);
        };

        $hasil = $daftar
            ->filter(fn ($r) => ! $keadaanKini || $KEADAAN[$keadaanKini]['uji']($r))
            ->filter(fn ($r) => $stBerlaku === 'semua' || ($r->jenis()->melewatiSiptl()
                && TindakLanjutRingkas::kemajuan($r, $peran, $satkerAktif)['rangkuman'] === $stBerlaku))
            ->filter(fn ($r) => $sk === 'semua' || $r->daftarSasaran()->contains('satker_id', (int) $sk))
            ->filter($cocokTeks)->values();

        /* Pembaruan = pemberitahuan yang menyangkut pengguna ini; yang belum dibaca diberi titik merah. */
        $pembaruan = $sorotBaru || $urut === 'terbaru'
            ? Pemberitahuan::perRekomendasi($u, $hasil->pluck('id')->all()) : ['terakhir' => [], 'belum' => []];

        // Hitung sekali per hasil, bukan pada setiap perbandingan saat mengurutkan.
        $kunci = $hasil->mapWithKeys(function ($r) use ($urut, $peran, $satkerAktif, $pembaruan) {
            $satker = $urut === 'satker' ? $r->satkerTampil($peran, $satkerAktif)->map->nama : null;

            return [$r->id => [
                'urgensi' => $r->urgensi(),
                'nilai' => match ($urut) {
                    'keterangan' => $r->asalData(),
                    'uraian' => $r->uraian,
                    'tenggat' => (string) $r->tenggat_jawab?->toDateString(),
                    'satker' => [(string) $satker->first(), $satker->count()],
                    'kemajuan' => TindakLanjutRingkas::bagian($r, $peran, $satkerAktif),
                    /* Belum pernah ada pemberitahuan: tanggal dicatatnya. */
                    'terbaru' => max(($pembaruan['terakhir'][$r->id] ?? null)?->getTimestamp() ?? 0,
                        $r->created_at?->getTimestamp() ?? 0),
                    default => 0,
                },
            ]];
        });
        $hasil = $hasil->sort(function ($a, $b) use ($urut, $arah, $kunci) {
            $ka = $kunci[$a->id];
            $kb = $kunci[$b->id];
            $mendesak = $kb['urgensi'] <=> $ka['urgensi'];
            if ($urut === 'mendesak') {
                return $mendesak;
            }
            if ($urut === 'terbaru') {
                return ($kb['nilai'] <=> $ka['nilai']) ?: $mendesak;
            }
            $banding = match ($urut) {
                'keterangan', 'uraian', 'tenggat' => strcmp($ka['nilai'], $kb['nilai']),
                'satker' => strcmp($ka['nilai'][0], $kb['nilai'][0]) ?: $ka['nilai'][1] <=> $kb['nilai'][1],
                default => $ka['nilai'] <=> $kb['nilai'],
            };

            return ($arah === 'turun' ? -$banding : $banding) ?: $mendesak;
        })
            ->values();

        return view('rekomendasi.index', [
            'hasil' => $hasil,
            'daftar' => $daftar,
            'lingkup' => $lingkup,
            'jumlahJenis' => $jumlahJenis,
            'KEADAAN' => $KEADAAN,
            'keadaanAda' => $keadaanAda,
            'keadaanKini' => $keadaanKini,
            'jumlah' => $jumlah,
            'perluKerjaMenu' => $jumlah['kerja'],
            'q' => $q,
            'sk' => $sk,
            'st' => $st,
            'urut' => $urut,
            'arah' => $arah,
            'urutBawaan' => $urutBawaan,
            'sorotBaru' => $sorotBaru,
            'belumDilihat' => $sorotBaru ? $pembaruan['belum'] : [],
            'perlu' => $hasil->filter(fn ($r) => $r->perluPerhatian())->count(),
            /* Satuan kerja dan pimpinan satu Satker tidak diberi filter; pimpinan beberapa Satker hanya Satker-nya. */
            'daftarSatker' => $peran->terbatasSatker() && ! is_array($satkerAktif) ? collect() : $terlihat->satkers()->orderBy('id')->get(),
            'STATUS' => StatusTindakLanjut::cases(),
        ]);
    }

    public function show(Request $req, Rekomendasi $rekomendasi)
    {
        return app(RincianController::class)->tampil($req, $rekomendasi);
    }

    /**
     * Dari baris bertitik merah: semua pembaruan rekomendasi ini yang belum dilihat ditandai dibaca,
     * lalu rinciannya dibuka di bagian yang paling baru berubah (disorot).
     */
    public function terbaru(Rekomendasi $rekomendasi)
    {
        $u = auth()->user();
        abort_unless(Terlihat::untuk()->bolehLihatRekomendasi($rekomendasi), 403,
            'Rekomendasi ini tidak ditujukan ke satuan kerja Anda.');

        $belum = Pemberitahuan::perRekomendasi($u, [$rekomendasi->id])['belum'][$rekomendasi->id] ?? collect();
        Pemberitahuan::tandaiBanyak($belum, $u);

        return redirect()->route('rekomendasi.show', $belum->isNotEmpty()
            ? Pemberitahuan::tujuan($belum->first(), $u) : ['rekomendasi' => $rekomendasi->id]);
    }
}
