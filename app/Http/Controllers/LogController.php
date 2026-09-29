<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Models\LogAktivitas;
use App\Models\User;
use App\Support\Aktivitas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Log aktivitas — layar DTI (dan Admin). Padanan `LayarLog` prototipe (27 Sep).
 *
 * Bang Kamal: "DTI itu hanya untuk melihat jika dia merubah data, merusak
 * data, kita tinggal nembak siapa pelakunya … Hanya kategori menjaga data."
 * Dan: "Puspi, kenapa lu kagak pakai-pakai nih? Log lu mati, ada apa?"
 *
 * Maka dua tab:
 * - Aktivitas — siapa berbuat apa, kapan, pada berkas mana; difilter menurut
 *   waktu, kelompok, peran, dan kata kunci; bisa diunduh CSV;
 * - Keaktifan akun — tiap akun aktif, kapan terakhir masuk dan berbuat
 *   sesuatu. Yang lama diam muncul paling atas.
 *
 * Hanya membaca. Tidak ada jalur mengubah atau menghapus log.
 */
class LogController extends Controller
{
    private const RENTANG = ['7' => '7 hari', '30' => '30 hari', '90' => '90 hari', 'semua' => 'Semua waktu'];

    /** Diam lebih lama dari ini ditandai di tab Keaktifan akun. */
    public const HARI_DIAM = 14;

    public function index(Request $r)
    {
        $this->pastikanBoleh($r);
        $f = $this->filter($r);

        if ($f['tab'] === 'keaktifan') {
            return view('log', ['f' => $f, 'rentang' => self::RENTANG, 'keaktifan' => $this->keaktifan()]);
        }

        $log = $this->kueri($f)->with('satker')->orderByDesc('waktu')->orderByDesc('id')
            ->paginate((int) config('simtlhp.log.per_halaman', 50))->withQueryString();

        return view('log', [
            'f' => $f, 'rentang' => self::RENTANG, 'log' => $log,
            'subjek' => Aktivitas::subjek($log->getCollection()),
        ]);
    }

    /** Hasil filter yang sama, sebagai CSV — untuk diperiksa di luar aplikasi. */
    public function unduh(Request $r)
    {
        $this->pastikanBoleh($r);
        $f = $this->filter($r);
        Aktivitas::catat('log.unduh', 'Mengunduh log aktivitas ('.self::RENTANG[$f['rentang']].')', [
            'kelompok' => 'akses', 'rincian' => array_filter($f),
        ]);
        $kueri = $this->kueri($f)->orderByDesc('waktu')->orderByDesc('id');

        return response()->streamDownload(function () use ($kueri) {
            $o = fopen('php://output', 'w');
            fwrite($o, "\xEF\xBB\xBF");
            fputcsv($o, ['Waktu', 'Nama', 'Peran', 'Unit kerja', 'Kelompok', 'Aktivitas', 'Objek', 'Alamat IP', 'Perangkat', 'Rincian'], ';');
            $kueri->with('satker')->chunk(500, function ($baris) use ($o) {
                $sub = Aktivitas::subjek($baris);
                foreach ($baris as $x) {
                    fputcsv($o, [
                        $x->waktu?->format('Y-m-d H:i:s'), $x->nama, $x->sebutanPeran(), $x->satker?->namaPendek(),
                        Aktivitas::KELOMPOK[$x->kelompok] ?? $x->kelompok, $x->ringkasan,
                        $sub[$x->subjek_tipe.':'.$x->subjek_id]['teks'] ?? '', $x->ip, $x->agen,
                        $x->rincian ? json_encode($x->rincian, JSON_UNESCAPED_UNICODE) : '',
                    ], ';');
                }
            });
            fclose($o);
        }, 'log-aktivitas-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ================================================================ */

    private function filter(Request $r): array
    {
        $tab = $r->query('tab') === 'keaktifan' ? 'keaktifan' : 'aktivitas';
        $rentang = (string) $r->query('rentang', 'semua');

        return [
            'tab'      => $tab,
            'rentang'  => array_key_exists($rentang, self::RENTANG) ? $rentang : 'semua',
            'kelompok' => array_key_exists((string) $r->query('kelompok'), Aktivitas::KELOMPOK) ? (string) $r->query('kelompok') : '',
            'peran'    => PeranPengguna::tryFrom((string) $r->query('peran'))?->value ?? '',
            'q'        => mb_substr(trim((string) $r->query('q', '')), 0, 100),
            'akun'     => $r->integer('akun') ?: null,
        ];
    }

    private function kueri(array $f): Builder
    {
        return LogAktivitas::query()
            ->when($f['rentang'] !== 'semua', fn ($q) => $q->where('waktu', '>=', now()->subDays((int) $f['rentang'])->startOfDay()))
            ->when($f['kelompok'], fn ($q, $k) => $q->where('kelompok', $k))
            ->when($f['peran'], fn ($q, $p) => $q->where('peran', $p))
            ->when($f['akun'], fn ($q, $id) => $q->where('user_id', $id))
            ->when($f['q'] !== '', function ($q) use ($f) {
                $kata = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['q']).'%';
                $q->where(fn ($w) => $w->where('ringkasan', 'like', $kata)->orWhere('nama', 'like', $kata)->orWhere('ip', 'like', $kata));
            });
    }

    /**
     * Tiap akun aktif: terakhir masuk, terakhir berbuat sesuatu, dan berapa
     * perbuatannya dalam 30 hari. Satu kueri teragregasi, bukan satu per akun.
     */
    private function keaktifan(): Collection
    {
        $batas = now()->subDays(30);
        $agregat = LogAktivitas::query()
            ->whereNotNull('user_id')->whereNotIn('aksi', ['masuk', 'keluar'])
            ->groupBy('user_id')
            ->selectRaw('user_id, max(waktu) as terakhir, sum(case when waktu >= ? then 1 else 0 end) as n30',
                [$batas->format('Y-m-d H:i:s')])
            ->get()->keyBy('user_id');

        return User::with('satker')->where('aktif', true)->get()
            ->map(function ($u) use ($agregat) {
                $a = $agregat[$u->id] ?? null;
                $terakhir = $a?->terakhir ? \Illuminate\Support\Carbon::parse($a->terakhir) : null;
                /* Dibaca sebagai tanggal apa pun bentuk asalnya — dulu teks
                   dari basis data membuat halaman ini error (27 Sep). */
                $masuk = $u->terakhir_masuk_pada ? \Illuminate\Support\Carbon::parse($u->terakhir_masuk_pada) : null;
                $acuan = $terakhir && $masuk ? ($terakhir->greaterThan($masuk) ? $terakhir : $masuk) : ($terakhir ?? $masuk);

                return (object) [
                    'akun' => $u,
                    'terakhir_berbuat' => $terakhir,
                    'n30' => (int) ($a->n30 ?? 0),
                    'diam' => $acuan ? (int) $acuan->copy()->startOfDay()->diffInDays(now()->startOfDay()) : null,
                ];
            })
            ->sortBy([fn ($a, $b) => ($b->diam ?? PHP_INT_MAX) <=> ($a->diam ?? PHP_INT_MAX)])
            ->values();
    }

    private function pastikanBoleh(Request $r): void
    {
        abort_unless($r->user()->peran->bacaLog(), 403, 'Log aktivitas hanya dibuka DTI dan Admin.');
    }
}
