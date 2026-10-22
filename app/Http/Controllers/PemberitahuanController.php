<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Models\Notifikasi;
use App\Models\User;
use App\Support\Pemberitahuan;
use App\Support\Rangka;
use Illuminate\Http\Request;

/**
 * Pemberitahuan: BARU (angka lonceng), BELUM DIBACA, SUDAH DIBACA. Membuka = dibaca; bisa ditandai
 * belum dibaca lagi.
 */
class PemberitahuanController extends Controller
{
    /** Pemberitahuan yang baru saat daftarnya terakhir dibuka — untuk lencana "Baru". */
    private const BARU_TADI = 'pemberitahuan_baru_tadi';

    /** Jenis yang dipilih di filter halaman, atau null bila tidak dikenal. */
    private function jenis(Request $req): ?string
    {
        $j = $req->input('jenis');

        return is_string($j) && isset(Pemberitahuan::JENIS[$j]) ? $j : null;
    }

    /** Halaman Pemberitahuan; membukanya menandai semua sudah dilihat. Bisa disaring per jenis. */
    public function index(Request $req)
    {
        $u = auth()->user();
        $jenis = $this->jenis($req);
        $d = Pemberitahuan::daftarTerbatas($u, Pemberitahuan::BATAS_HALAMAN, $jenis);
        $hitung = Pemberitahuan::hitungJenis($u);
        /* Angka tab dan kosong mengikuti yang tampil: tersaring bila satu jenis dipilih. */
        $belum = $jenis ? ($hitung['belum'][$jenis] ?? 0) : $d['belum'];
        $semuaTampil = $jenis ? ($hitung['semua'][$jenis] ?? 0) : $d['semua'];

        $baru = Pemberitahuan::idBaru($u);
        if ($baru) {
            session([self::BARU_TADI => $baru]);
            Pemberitahuan::catatDilihat($u);
        }

        $tab = in_array($req->query('tab'), ['belum', 'semua'], true) ? $req->query('tab') : ($belum ? 'belum' : 'semua');

        return view('pemberitahuan', [
            'daftar' => $d['daftar'], 'semuaId' => $d['semuaId'], 'tab' => $tab,
            'belumSemua' => $d['belum'], 'semua' => $d['semua'], 'belum' => $belum, 'semuaTampil' => $semuaTampil,
            'jenis' => $jenis, 'hitungJenis' => $hitung,
            'kosong' => $tab === 'belum' ? $belum === 0 : $semuaTampil === 0,
            'baruTadi' => session(self::BARU_TADI, []),
        ]);
    }

    /** Isi panel lonceng; yang baru diberi lencana lalu dicatat sudah dilihat. */
    public function panel()
    {
        $u = auth()->user();
        $d = Pemberitahuan::daftarTerbatas($u, Pemberitahuan::BATAS_PANEL);
        $baru = Pemberitahuan::idBaru($u);
        session([self::BARU_TADI => $baru]);
        Pemberitahuan::catatDilihat($u);
        $tab = $d['belum'] ? 'belum' : 'semua';

        return view('bagian.pemberitahuan-panel', $d + [
            'tab' => $tab,
            'kosong' => $tab === 'belum' ? ! $d['belum'] : ! $d['semua'],
            'baruTadi' => $baru,
        ]);
    }

    /**
     * Dari pemberitahuan langsung ke bagian yang berubah (ditandai dibaca, baris satuan kerja
     * disorot).
     */
    public function buka(Notifikasi $notifikasi)
    {
        $u = auth()->user();
        if (! Pemberitahuan::boleh($notifikasi, $u)) {
            /* Email bisa dibuka saat peran aktifnya lain: beralih lewat tombol, tidak diam-diam. */
            $peran = Pemberitahuan::peranLain($notifikasi, $u);
            abort_unless($peran, 403);

            return view('pemberitahuan-peran', ['n' => $notifikasi, 'peran' => $peran, 'sekarang' => $u->peranAktif()]);
        }
        Pemberitahuan::tandai($notifikasi, $u, true);

        /* Perubahan sistem: entri Log admin-nya, terbuka. */
        if ($notifikasi->sistem()) {
            return redirect()->route('log', ['kejadian' => $notifikasi->log_aktivitas_id, 'rinci' => $notifikasi->log_aktivitas_id]);
        }

        return redirect()->route('rekomendasi.show', Pemberitahuan::tujuan($notifikasi, $u));
    }

    /** Tanda dibaca satu pemberitahuan, dipasang (`baca=1`) atau dilepas (`baca=0`). */
    public function tandai(Request $req, Notifikasi $notifikasi)
    {
        $u = auth()->user();
        abort_unless(Pemberitahuan::boleh($notifikasi, $u), 403);
        Pemberitahuan::tandai($notifikasi, $u, $req->boolean('baca'));

        return $req->ajax() ? response()->json($this->hitungan($u)) : back();
    }

    public function tandaiSemua(Request $req)
    {
        $u = auth()->user();
        Pemberitahuan::tandaiSemua($u, $this->jenis($req));

        return $req->ajax() ? response()->json($this->hitungan($u)) : back();
    }

    /** Ditanya skrip tiap menit: angka lonceng dan pop-up pemberitahuan baru. */
    public function ringkas()
    {
        $u = auth()->user();
        $baru = Pemberitahuan::jumlahBaru($u);
        $s = Rangka::popupPemberitahuan($u, $baru);

        return response()->json(['baru' => $baru, 'belum' => Pemberitahuan::belumDibaca($u)] + [
            'popup' => $s ? view('bagian.pemberitahuan-popup', ['k' => $s['pemberitahuan'], 'jumlah' => $s['jumlah']])->render() : null,
        ]);
    }

    private function hitungan(User $u): array
    {
        return ['baru' => Pemberitahuan::jumlahBaru($u), 'belum' => Pemberitahuan::belumDibaca($u)];
    }
}
