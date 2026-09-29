<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Models\Notifikasi;
use App\Models\User;
use App\Support\Pemberitahuan;
use App\Support\Rangka;
use Illuminate\Http\Request;

/**
 * Pemberitahuan — padanan `PanelPemberitahuan`, `Pemberitahuan`, `bukaPemberitahuan`,
 * `tandaiBaca`, dan `tandaiTerbaca` prototipe.
 *
 * Dirombak 26 Sep. Kata Hizkia: disesuaikan ulang "dari segi visualisasi,
 * peningkatan fungsional yang lebih dinamis, lokasi … pop up … yang lebih baik
 * dan strategis dan juga logika kondisi Notifikasi saat di klik atau belum
 * dilihat". Tiga keadaan: BARU (belum pernah tampil di daftar — angka di
 * lonceng), BELUM DIBACA (titik biru), SUDAH DIBACA. Membuka pemberitahuan kini
 * menandainya dibaca; pemberitahuan yang dibaca tetap ada di tab "Semua", dan bisa
 * ditandai belum dibaca lagi dari barisnya.
 */
class PemberitahuanController extends Controller
{
    /** Pemberitahuan yang baru saat daftarnya terakhir dibuka — untuk lencana "Baru". */
    private const BARU_TADI = 'pemberitahuan_baru_tadi';

    /**
     * Halaman Pemberitahuan: riwayat lengkapnya. Membukanya menandai semua
     * yang ada sudah dilihat; lencana "Baru" yang tadi tampil di panel ikut ke
     * sini (padanan `keHalamanPemberitahuan`).
     */
    public function index(Request $req)
    {
        $u = auth()->user();
        $daftar = Pemberitahuan::untuk($u);
        $belum = $daftar->reject(fn ($k) => Pemberitahuan::sudahDibaca($k, $u))->count();

        $baru = Pemberitahuan::idBaru($u);
        if ($baru) {
            session([self::BARU_TADI => $baru]);
            Pemberitahuan::catatDilihat($u);
        }

        $tab = in_array($req->query('tab'), ['belum', 'semua'], true) ? $req->query('tab') : ($belum ? 'belum' : 'semua');

        return view('pemberitahuan', [
            'daftar' => $daftar, 'belum' => $belum, 'semua' => $daftar->count(), 'tab' => $tab,
            'kosong' => $tab === 'belum' ? $belum === 0 : $daftar->isEmpty(),
            'baruTadi' => session(self::BARU_TADI, []),
        ]);
    }

    /**
     * Isi panel di bawah lonceng, diambil skrip saat lonceng ditekan. Yang baru
     * diberi lencana, lalu semuanya dicatat sudah dilihat — angka di lonceng
     * hilang (padanan `bukaPanelPemberitahuan`). 40 pemberitahuan terbaru untuk tab "Semua",
     * ditambah yang belum dibaca untuk tab "Belum dibaca".
     */
    public function panel()
    {
        $u = auth()->user();
        $semua = Pemberitahuan::untuk($u);
        $baru = Pemberitahuan::idBaru($u);
        session([self::BARU_TADI => $baru]);
        Pemberitahuan::catatDilihat($u);

        $belum = $semua->reject(fn ($k) => Pemberitahuan::sudahDibaca($k, $u))->values();
        $terbaru = $semua->take(40);
        $tab = $belum->isNotEmpty() ? 'belum' : 'semua';

        return view('bagian.pemberitahuan-panel', [
            'daftar' => $terbaru->concat($belum->take(40))->unique('id')->values(),
            'semuaId' => $terbaru->pluck('id')->all(),
            'belum' => $belum->count(), 'semua' => $semua->count(), 'tab' => $tab,
            'kosong' => $tab === 'belum' ? $belum->isEmpty() : $semua->isEmpty(),
            'baruTadi' => $baru,
        ]);
    }

    /**
     * Dari pemberitahuan langsung ke bagian yang berubah: menandainya dibaca, membuka
     * rincian rekomendasinya, lalu menunjuk bagiannya — baris satuan kerja
     * yang diberitahukan dibuka dan disorot.
     */
    public function buka(Notifikasi $notifikasi)
    {
        $u = auth()->user();
        abort_unless(Pemberitahuan::boleh($notifikasi, $u), 403);
        Pemberitahuan::tandai($notifikasi, $u, true);

        /* Satuan kerja cuma dituju barisnya sendiri, walau pemberitahuannya dikirim ke
           beberapa satuan kerja sekaligus. Peran lain: hanya kalau pemberitahuannya
           memang tentang SATU satuan kerja. */
        $satker = $u->peran === PeranPengguna::SATKER
            ? collect([$u->satker_id])
            : $notifikasi->satker()->pluck('satkers.id');

        return redirect()->route('rekomendasi.show', array_filter([
            'rekomendasi' => $notifikasi->rekomendasi_id,
            'sorot'       => $notifikasi->blok,
            'satker'      => $satker->count() === 1 ? $satker->first() : null,
            'tindakan'    => $notifikasi->tindakan_id,
        ]));
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

        foreach (Pemberitahuan::untuk($u) as $k) {
            if (! Pemberitahuan::sudahDibaca($k, $u)) {
                $k->dibaca()->attach($u->id, ['dibaca_pada' => now()]);
            }
        }

        return $req->ajax() ? response()->json($this->hitungan($u)) : back();
    }

    /**
     * Ditanya skrip berkala (tiap menit, selama tabnya terlihat): angka lonceng
     * terkini, dan kotak popup kalau ada pemberitahuan baru yang belum pernah
     * dimunculkan sebagai pop-up. Di prototipe pemberitahuan hanya datang saat berganti akun; di sini
     * orang lain bisa bertindak kapan saja.
     */
    public function ringkas()
    {
        $u = auth()->user();
        $s = Rangka::popupPemberitahuan($u, Pemberitahuan::jumlahBaru($u));

        return response()->json($this->hitungan($u) + [
            'popup' => $s ? view('bagian.pemberitahuan-popup', ['k' => $s['pemberitahuan'], 'jumlah' => $s['jumlah']])->render() : null,
        ]);
    }

    private function hitungan(User $u): array
    {
        return ['baru' => Pemberitahuan::jumlahBaru($u), 'belum' => Pemberitahuan::belumDibaca($u)];
    }
}
