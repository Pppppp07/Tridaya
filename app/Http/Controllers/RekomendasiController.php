<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Enums\StatusTindakLanjut;
use App\Models\Rekomendasi;
use App\Models\Satker;
use App\Support\Lingkup;
use App\Support\Terlihat;
use App\Support\TindakLanjutRingkas;
use Illuminate\Http\Request;

/**
 * Layar Rekomendasi (padanan `SemuaRekom`) dan halaman rinciannya.
 *
 * Satu layar untuk semua peran: keranjang di kepalanya menggantikan lima layar
 * lama yang isinya daftar sama difilter berbeda. Angka tiap keranjang dihitung
 * dari daftar penuh, bukan dari hasil filter — kalau ikut terfilter, angkanya
 * berubah tiap kali orang mengetik.
 */
class RekomendasiController extends Controller
{
    public function beranda()
    {
        /* Bawaannya menurut peran — Pimpinan di Dashboard, DTI di Log aktivitas
           (27 Sep), lainnya di Rekomendasi — kecuali akunnya memilih halaman
           pertama sendiri di Profil → Tampilan (28 Sep). */
        return redirect()->route(\App\Support\Setelan::ruteBeranda(auth()->user()));
    }

    /** Muatan yang dibutuhkan hitungan daftar: meja, kemajuan, tenggat, urgensi. */
    public const MUAT_DAFTAR = [
        'temuan.laporan', 'sasaran.satker', 'tindakan.bentuk',
        'pemulihan', 'tolakanBpk', 'permintaanDokumen.item', 'riwayat',
    ];

    public function index(Request $req)
    {
        $u = auth()->user();
        $peran = $u->peran;
        $satkerAktif = $u->satker_id;
        $terlihat = Terlihat::untuk();
        $lingkup = Lingkup::dari($req);

        /* Urutan dasar = urutan pencatatan (laporan, temuan, rekomendasi),
           sama dengan prototipe; yang seri pada urutan mana pun jatuh ke sini. */
        $utuh = $terlihat->rekomendasi()->with(self::MUAT_DAFTAR)->orderBy('rekomendasis.id')->get()
            ->map(fn ($r) => $terlihat->pangkasRekomendasi($r));

        /* Dihitung sebelum lingkup difilter: kalau dari daftar yang sudah
           difilter, membuka LHP membuat tombol LHA menulis nol — dan tombol
           yang menulis nol tidak bisa lagi dipakai untuk pindah ke sana. */
        $jumlahJenis = [
            'semua' => $utuh->count(),
            'LHP'   => $utuh->filter(fn ($r) => $r->jenis()->value === 'LHP')->count(),
            'LHA'   => $utuh->filter(fn ($r) => $r->jenis()->value === 'LHA')->count(),
        ];

        $daftar = $utuh->filter(fn ($r) => Lingkup::berlaku($lingkup, $r->jenis()))->values();

        $KEADAAN = [
            'kerja'   => ['nama' => 'Perlu dikerjakan',
                          'uji' => fn ($r) => $r->diMeja($peran, $satkerAktif)],
            'tunggu'  => ['nama' => 'Sedang menunggu',
                          'uji' => fn ($r) => ! $r->beres() && ! $r->diMeja($peran, $satkerAktif)],
            'selesai' => ['nama' => 'Sudah selesai', 'uji' => fn ($r) => $r->beres()],
            /* Potongan dari "Perlu saya kerjakan", dikendalikan sistem luar.
               Hanya Setba, dan hanya lingkup yang memuat LHP. */
            'siptl'   => ['nama' => 'Urusan SIPTL', 'khusus' => PeranPengguna::SETBA, 'jenis' => 'LHP', 'ikon' => 'Landmark',
                          'uji' => fn ($r) => $r->kerjaSiptl()],
        ];
        $keadaanAda = array_filter($KEADAAN, fn ($v) => (! isset($v['khusus']) || $v['khusus'] === $peran)
            && (! isset($v['jenis']) || $lingkup === 'semua' || $lingkup === $v['jenis']));
        $jumlah = array_map(fn ($v) => $daftar->filter($v['uji'])->count(), $KEADAAN);

        /* Keadaan bawaan mengikuti perannya: yang mengerjakan berkas dibukakan
           pekerjaannya, yang cuma memantau dibukakan semuanya. Keranjang yang
           tombolnya tidak ada dilepas — yang tampil jadi seluruhnya. */
        $keadaan = $req->query('keadaan', $peran->hanyaMelihat() ? 'semua' : 'kerja');
        $keadaanKini = isset($keadaanAda[$keadaan]) ? $keadaan : null;

        /* `tuju` (26 Sep): kembali dari rincian, atau sesudah tindakan — kirim,
           teruskan, putusan. Barisnya disorot skrip (pasangPintasan). Kalau
           berkasnya sudah pindah keranjang (dikirim: dari "Perlu saya kerjakan"
           ke "Sedang menunggu"), keranjangnya ikut dipindah — sama dengan
           prototipe. */
        $tuju = (int) $req->query('tuju', 0);
        $rekTuju = $tuju ? $daftar->firstWhere('id', $tuju) : null;
        if ($rekTuju && $keadaanKini && ! $KEADAAN[$keadaanKini]['uji']($rekTuju)) {
            $keadaanKini = collect(array_keys($keadaanAda))
                ->first(fn ($k) => ! isset($keadaanAda[$k]['khusus']) && $KEADAAN[$k]['uji']($rekTuju));
        }

        $q = trim((string) $req->query('q', ''));
        $sk = $req->query('sk', 'semua');
        $st = $req->query('st', 'semua');
        $stBerlaku = $lingkup === 'LHA' ? 'semua' : $st;
        [$urut, $arah] = array_pad(explode(':', (string) $req->query('urut', 'mendesak')), 2, 'naik');
        if (! in_array($urut, ['mendesak', 'uraian', 'satker', 'tenggat', 'kemajuan'], true)) {
            $urut = 'mendesak';
        }

        $cocokTeks = function (Rekomendasi $r) use ($q) {
            if ($q === '') {
                return true;
            }
            $t = $r->temuan;
            $teks = implode(' ', [$r->kode, $t->judul, $r->uraian, $r->refIdt(),
                $t->laporan->nomor, $r->refLhp(), $r->daftarSasaran()->first()?->satker?->namaPendek()]);

            return str_contains(mb_strtolower($teks), mb_strtolower($q));
        };

        $hasil = $daftar
            ->filter(fn ($r) => ! $keadaanKini || $KEADAAN[$keadaanKini]['uji']($r))
            ->filter(fn ($r) => $stBerlaku === 'semua' || ($r->jenis()->melewatiSiptl()
                && TindakLanjutRingkas::kemajuan($r, $peran, $satkerAktif)['rangkuman'] === $stBerlaku))
            ->filter(fn ($r) => $sk === 'semua' || $r->daftarSasaran()->contains('satker_id', (int) $sk))
            ->filter($cocokTeks)
            ->sort(function ($a, $b) use ($urut, $arah, $peran, $satkerAktif) {
                $mendesak = $b->urgensi() <=> $a->urgensi();
                if ($urut === 'mendesak') {
                    return $mendesak;
                }
                $banding = match ($urut) {
                    'uraian'   => strcmp($a->uraian, $b->uraian),
                    'tenggat'  => strcmp((string) $a->tenggat_jawab?->toDateString(), (string) $b->tenggat_jawab?->toDateString()),
                    'satker'   => (function () use ($a, $b, $peran, $satkerAktif) {
                        $na = $a->satkerTampil($peran, $satkerAktif)->map->nama;
                        $nb = $b->satkerTampil($peran, $satkerAktif)->map->nama;

                        return strcmp((string) $na->first(), (string) $nb->first()) ?: $na->count() <=> $nb->count();
                    })(),
                    default    => TindakLanjutRingkas::bagian($a, $peran, $satkerAktif)
                                  <=> TindakLanjutRingkas::bagian($b, $peran, $satkerAktif),
                };

                return ($arah === 'turun' ? -$banding : $banding) ?: $mendesak;
            })
            ->values();

        return view('rekomendasi.index', [
            'hasil'       => $hasil,
            'daftar'      => $daftar,
            'lingkup'     => $lingkup,
            'jumlahJenis' => $jumlahJenis,
            'KEADAAN'     => $KEADAAN,
            'keadaanAda'  => $keadaanAda,
            'keadaanKini' => $keadaanKini,
            'jumlah'      => $jumlah,
            'q'           => $q,
            'sk'          => $sk,
            'st'          => $st,
            'urut'        => $urut,
            'arah'        => $arah,
            'perlu'       => $hasil->filter(fn ($r) => $r->perluPerhatian())->count(),
            'daftarSatker'=> $peran === PeranPengguna::SATKER ? collect() : Satker::orderBy('id')->get(),
            'STATUS'      => StatusTindakLanjut::cases(),
        ]);
    }

    public function show(Request $req, Rekomendasi $rekomendasi)
    {
        return app(RincianController::class)->tampil($req, $rekomendasi);
    }
}
