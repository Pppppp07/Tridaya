<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Models\AlertKeamanan;
use App\Models\LogAktivitas;
use App\Models\User;
use App\Support\Aktivitas;
use App\Support\AlertKeamanan as PantauKeamanan;
use App\Support\TampilanLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Log aktivitas (DTI dan Admin): Alert keamanan, Aktivitas, Keaktifan akun. Log sumber hanya
 * dibaca; tinjauan alert disimpan terpisah.
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

        $bersama = ['f' => $f, 'rentang' => self::RENTANG,
            'jumlahAlert' => AlertKeamanan::whereIn('status', ['baru', 'perlu_tindak_lanjut'])->count(),
        ];
        if ($f['tab'] === 'alert') {
            $alerts = AlertKeamanan::query()
                ->when($f['rentang'] !== 'semua', fn ($q) => $q->where('terakhir_pada', '>=', Aktivitas::waktuKini()->subDays((int) $f['rentang'])->startOfDay()))
                ->when($f['status'], fn ($q, $v) => $v === 'terbuka' ? $q->whereIn('status', ['baru', 'perlu_tindak_lanjut']) : $q->where('status', $v))
                ->when($f['tingkat'], fn ($q, $v) => $q->where('tingkat', $v))
                ->when($f['q'] !== '', function ($q) use ($f) {
                    $kata = $this->kata($f['q']);
                    $q->where(fn ($w) => $w->where('judul', 'like', $kata)->orWhere('nama', 'like', $kata)->orWhere('ip', 'like', $kata));
                })
                ->orderByDesc('terakhir_pada')->orderByDesc('id')
                ->paginate((int) config('simtlhp.log.per_halaman', 50))->withQueryString();
            $alert = $r->integer('alert') ? AlertKeamanan::with('tinjauan')->findOrFail($r->integer('alert')) : null;
            $bukti = $alert?->logBukti() ?? collect();

            return view('log', $bersama + [
                'alerts' => $alerts, 'alert' => $alert, 'bukti' => $bukti,
                'subjek' => Aktivitas::subjek($bukti), 'mulaiPantau' => PantauKeamanan::mulaiPada(),
            ]);
        }
        if ($f['tab'] === 'keaktifan') {
            return view('log', $bersama + ['keaktifan' => $this->keaktifan()]);
        }

        $log = $this->kueri($f)->with('satker')->orderByDesc('waktu')->orderByDesc('id')
            ->paginate((int) config('simtlhp.log.per_halaman', 50))->withQueryString();

        $alertLog = [];
        $id = $log->getCollection()->pluck('id');
        if ($id->isNotEmpty()) {
            $terkait = AlertKeamanan::whereHas('logs', fn ($q) => $q->whereIn('log_aktivitas.id', $id))
                ->with(['logs' => fn ($q) => $q->whereIn('log_aktivitas.id', $id)])->orderByDesc('id')->get();
            foreach ($terkait as $a) {
                foreach ($a->logs as $kejadian) {
                    $alertLog[$kejadian->id][] = $a;
                }
            }
        }

        return view('log', $bersama + [
            'log' => $log, 'alertLog' => $alertLog,
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
            fputcsv($o, ['Waktu', 'Nama', 'Peran', 'Satuan kerja tertaut', 'Kelompok', 'Aktivitas', 'Target', 'Hasil', 'Asal kegiatan', 'Metode', 'Alamat permintaan', 'Rute', 'Status HTTP', 'Alamat IP', 'Perangkat', 'Rincian'], ';');
            $kueri->with('satker')->chunk(500, function ($baris) use ($o) {
                $sub = Aktivitas::subjek($baris);
                foreach ($baris as $x) {
                    $p = TampilanLog::permintaan($x);
                    fputcsv($o, array_map([TampilanLog::class, 'selCsv'], [
                        $x->waktu?->format('Y-m-d H:i:s'), $x->nama, $x->sebutanPeran(), $x->satker?->namaPendek(),
                        Aktivitas::KELOMPOK[$x->kelompok] ?? $x->kelompok, $x->ringkasan,
                        $sub[$x->subjek_tipe.':'.$x->subjek_id]['teks'] ?? '', TampilanLog::hasil($x)['nama'], TampilanLog::asal($x),
                        $p['metode'], $p['alamat'], $p['rute'], $p['status'], $x->ip, $x->agen,
                        $x->rincian ? json_encode($x->rincian, JSON_UNESCAPED_UNICODE) : '',
                    ]), ';');
                }
            });
            fclose($o);
        }, 'log-aktivitas-'.Aktivitas::waktuKini()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ================================================================ */

    private function filter(Request $r): array
    {
        $tab = in_array($r->query('tab'), ['keaktifan', 'alert'], true) ? $r->query('tab') : 'aktivitas';
        $rentang = (string) $r->query('rentang', 'semua');

        return [
            'tab' => $tab,
            'rentang' => array_key_exists($rentang, self::RENTANG) ? $rentang : 'semua',
            'kelompok' => array_key_exists((string) $r->query('kelompok'), Aktivitas::KELOMPOK) ? (string) $r->query('kelompok') : '',
            'peran' => PeranPengguna::tryFrom((string) $r->query('peran'))?->value ?? '',
            'q' => mb_substr(trim((string) $r->query('q', '')), 0, 100),
            'akun' => $r->integer('akun') ?: null,
            'kejadian' => max(0, $r->integer('kejadian')) ?: null,
            'status' => in_array($r->query('status'), ['terbuka', 'baru', 'wajar', 'perlu_tindak_lanjut', 'selesai'], true) ? $r->query('status') : ($tab === 'alert' && ! $r->has('status') ? 'terbuka' : ''),
            'tingkat' => in_array($r->query('tingkat'), ['tinggi', 'perhatian'], true) ? $r->query('tingkat') : '',
        ];
    }

    private function kueri(array $f): Builder
    {
        return LogAktivitas::query()
            ->when($f['rentang'] !== 'semua', fn ($q) => $q->where('waktu', '>=', Aktivitas::waktuKini()->subDays((int) $f['rentang'])->startOfDay()))
            ->when($f['kelompok'], fn ($q, $k) => $q->where('kelompok', $k))
            ->when($f['peran'], fn ($q, $p) => $q->where('peran', $p))
            ->when($f['akun'], fn ($q, $id) => $q->where('user_id', $id))
            ->when($f['kejadian'], fn ($q, $id) => $q->whereKey($id))
            ->when($f['q'] !== '', function ($q) use ($f) {
                $q->where(function ($w) use ($f) {
                    foreach (TampilanLog::varianCari($f['q']) as $varian) {
                        $kata = $this->kata($varian);
                        $w->orWhere('ringkasan', 'like', $kata)->orWhere('nama', 'like', $kata)->orWhere('ip', 'like', $kata);
                    }
                });
            });
    }

    private function kata(string $teks): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $teks).'%';
    }

    /** Tiap akun aktif: terakhir masuk, terakhir berbuat, jumlah perbuatan 30 hari (satu kueri). */
    private function keaktifan(): Collection
    {
        $batas = Aktivitas::waktuKini()->subDays(30);
        $agregat = LogAktivitas::query()
            ->whereNotNull('user_id')->whereNotIn('aksi', ['masuk', 'keluar'])
            ->groupBy('user_id')
            ->selectRaw('user_id, max(waktu) as terakhir, sum(case when waktu >= ? then 1 else 0 end) as n30',
                [$batas->format('Y-m-d H:i:s')])
            ->get()->keyBy('user_id');

        return User::with('satker')->where('aktif', true)->get()
            ->map(function ($u) use ($agregat) {
                $a = $agregat[$u->id] ?? null;
                $terakhir = $a?->terakhir ? Carbon::parse($a->terakhir) : null;
                /* Dibaca sebagai tanggal apa pun bentuk asalnya. */
                $masuk = $u->terakhir_masuk_pada ? Carbon::parse($u->terakhir_masuk_pada) : null;
                $acuan = $terakhir && $masuk ? ($terakhir->greaterThan($masuk) ? $terakhir : $masuk) : ($terakhir ?? $masuk);

                return (object) [
                    'akun' => $u,
                    'terakhir_berbuat' => $terakhir,
                    'n30' => (int) ($a->n30 ?? 0),
                    'diam' => $acuan ? max(0, (int) $acuan->copy()->startOfDay()->diffInDays(Aktivitas::waktuKini()->startOfDay())) : null,
                ];
            })
            ->sortBy([fn ($a, $b) => ($b->diam ?? PHP_INT_MAX) <=> ($a->diam ?? PHP_INT_MAX)])
            ->values();
    }

    private function pastikanBoleh(Request $r): void
    {
        abort_unless($r->user()->peranAktif()->bacaLog(), 403, 'Log aktivitas hanya dibuka DTI dan Admin DTI.');
    }
}
