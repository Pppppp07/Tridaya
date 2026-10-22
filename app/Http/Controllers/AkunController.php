<?php

namespace App\Http\Controllers;

use App\Models\LogAktivitas;
use App\Models\User;
use App\Support\Aktivitas;
use App\Support\DirektoriIrm;
use App\Support\Perangkat;
use App\Support\Sesi;
use App\Support\Setelan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Profil & pengaturan akun yang sedang masuk (lima tab). Tidak ada jalur ke akun orang lain. */
class AkunController extends Controller
{
    public const TAB = ['profil', 'keamanan', 'pemberitahuan', 'tampilan', 'aktivitas'];

    private const PER_HALAMAN = 25;

    private const RENTANG = ['7' => '7 hari', '30' => '30 hari', '90' => '90 hari', 'semua' => 'Semua waktu'];

    public function index(Request $r)
    {
        $u = $r->user();
        $tab = in_array($r->query('tab'), self::TAB, true) ? $r->query('tab') : 'profil';

        return view('akun', ['tab' => $tab, 'u' => $u, 'setelan' => Setelan::untuk($u)] + match ($tab) {
            'profil' => $this->profil($u),
            'keamanan' => $this->keamanan($r, $u),
            'aktivitas' => $this->aktivitas($r, $u),
            default => [],
        });
    }

    /** Keluarkan satu perangkat lain, atau semuanya ("semua"). */
    public function sesi(Request $r)
    {
        abort_unless(Sesi::tersedia(), 404);
        $data = $r->validate(['sesi' => ['required', 'string', 'max:40']]);
        $keluar = Sesi::putus($r->user(), $r, $data['sesi']);

        if ($keluar->isEmpty()) {
            return redirect()->route('akun', ['tab' => 'keamanan'])->with('pesan_sesi', 'Perangkat itu sudah tidak masuk.');
        }
        $semua = $data['sesi'] === 'semua';
        Aktivitas::catat('akun.sesi', $semua
            ? 'Mengeluarkan '.$keluar->count().' perangkat lain dari akunnya'
            : 'Mengeluarkan '.$keluar->first().' dari akunnya', ['kelompok' => 'akun']);

        return redirect()->route('akun', ['tab' => 'keamanan'])
            ->with('pesan_sesi', $semua ? $keluar->count().' perangkat dikeluarkan.' : $keluar->first().' dikeluarkan.');
    }

    /** Simpan Pemberitahuan atau Tampilan. Hanya yang berubah yang dicatat. */
    public function setelan(Request $r)
    {
        $u = $r->user();
        abort_if($r->has('akun') && (string) $r->input('akun') !== (string) $u->id, 409, 'Sesi akun berubah. Muat ulang halaman.');
        $bagian = (string) $r->input('bagian');

        $baru = match ($bagian) {
            /* Saklar mati tidak terkirim; akun tanpa email tidak bisa menyalakannya. */
            'pemberitahuan' => ['popup' => $r->boolean('popup'), 'pembaruan' => $r->boolean('pembaruan'),
                'email' => $u->email ? $r->boolean('email') : Setelan::untuk($u)['email']],
            'tampilan' => $r->validateWithBag('setelan', [
                'tema' => ['sometimes', 'required', Rule::in(['terang', 'gelap'])],
                'menu' => ['sometimes', 'required', Rule::in(['otomatis', 'lebar', 'ringkas'])],
                'animasi' => ['required', Rule::in(['ikut', 'kurang'])],
                'beranda' => ['required', Rule::in(Setelan::berandaBoleh($u))],
            ], [
                'tema.*' => 'Pilih tema Terang atau Gelap.',
                'menu.*' => 'Pilih pengaturan menu samping.',
                'animasi.*' => 'Pilih pengaturan animasi.',
                'beranda.*' => 'Pilih halaman pertama dari daftar.',
            ]),
            default => abort(404),
        };

        $berubah = Setelan::simpan($u, $baru, $bagian);

        return redirect()->route('akun', ['tab' => $bagian])
            ->with('pesan_akun', $berubah ? 'Pengaturan tersimpan.' : 'Tidak ada yang berubah.');
    }

    /* ================================================================ */

    /** Pintasan hanya mengubah pilihan yang dikirim, selalu milik akun aktif. */
    public function tampilan(Request $r)
    {
        abort_if($r->has('akun') && (string) $r->input('akun') !== (string) $r->user()->id, 409, 'Sesi akun berubah. Muat ulang halaman.');
        $baru = $r->validate([
            'tema' => ['required_without:menu', Rule::in(['terang', 'gelap'])],
            'menu' => ['required_without:tema', Rule::in(['otomatis', 'lebar', 'ringkas'])],
        ]);
        Setelan::simpan($r->user(), $baru, 'tampilan');
        if ($r->expectsJson()) {
            return response()->json(['tampilan' => array_intersect_key(Setelan::untuk($r->user()), $baru)]);
        }

        return back()->with('pesan', 'Pengaturan tampilan tersimpan untuk akun Anda.');
    }

    private function profil(User $u): array
    {
        return [
            /* Unit organisasi dari direktori pegawai; halaman tetap terbuka bila direktori tak
               terjangkau. */
            'pegawai' => $u->nip ? DirektoriIrm::nip($u->nip) : null,
            'errorDirektori' => $u->nip ? DirektoriIrm::error() : null,
        ];
    }

    private function keamanan(Request $r, User $u): array
    {
        $email = $u->email ? mb_strtolower(trim($u->email)) : null;
        /* Percobaan gagal belum punya akun — dikenali dari email yang diketik (kolom `nama`). */
        $gagalDenganEmail = fn ($q) => $q->whereNull('user_id')->whereIn('aksi', ['masuk.gagal', 'masuk.dikunci'])
            ->where('nama', $email);

        $riwayat = LogAktivitas::query()
            ->where(function ($q) use ($u, $email, $gagalDenganEmail) {
                $q->where(fn ($q) => $q->where('user_id', $u->id)->whereIn('aksi', ['masuk', 'keluar']));
                if ($email) {
                    $q->orWhere($gagalDenganEmail);
                }
            })
            ->orderByDesc('waktu')->orderByDesc('id')->limit(10)->get();

        $sebelumnya = LogAktivitas::where('user_id', $u->id)->where('aksi', 'masuk')
            ->orderByDesc('waktu')->orderByDesc('id')->skip(1)->first();
        $gagal = $email ? LogAktivitas::query()->where($gagalDenganEmail)
            ->when($sebelumnya, fn ($q) => $q->where('waktu', '>', $sebelumnya->waktu))->count() : 0;

        /* Perangkat yang sedang dipakai selalu paling atas, walau sesinya belum tertulis ke
           tabel. */
        $sesi = Sesi::daftar($u, $r);
        if (! $sesi->contains('ini', true)) {
            $sesi->prepend((object) ['kunci' => '', 'ini' => true, 'perangkat' => Perangkat::sebut($r->userAgent()),
                'ip' => $r->ip(), 'detik' => 0]);
        }

        return [
            'riwayat' => $riwayat,
            'sebelumnya' => $sebelumnya,
            'gagal' => $gagal,
            'sesi' => $sesi,
            'sesiTersedia' => Sesi::tersedia(),
        ];
    }

    private function aktivitas(Request $r, User $u): array
    {
        $rentang = array_key_exists((string) $r->query('rentang'), self::RENTANG) ? (string) $r->query('rentang') : 'semua';
        $kelompok = array_key_exists((string) $r->query('kelompok'), Aktivitas::KELOMPOK) ? (string) $r->query('kelompok') : '';

        $log = LogAktivitas::where('user_id', $u->id)->whereNotIn('aksi', ['masuk', 'keluar'])
            ->when($rentang !== 'semua', fn ($q) => $q->where('waktu', '>=', now()->subDays((int) $rentang)->startOfDay()))
            ->when($kelompok, fn ($q, $k) => $q->where('kelompok', $k))
            ->orderByDesc('waktu')->orderByDesc('id')
            ->paginate(self::PER_HALAMAN)->withQueryString();

        return [
            'log' => $log,
            /* Akun di baris log hanya ditautkan bagi yang boleh membuka Log aktivitas. */
            'subjek' => Aktivitas::subjek($log->getCollection(), $u->peranAktif()->bacaLog()),
            'f' => ['rentang' => $rentang, 'kelompok' => $kelompok],
            'rentang' => self::RENTANG,
        ];
    }
}
