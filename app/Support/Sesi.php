<?php

namespace App\Support;

use App\Models\User;
use App\Support\Sso\Penyedia;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Perangkat yang sedang masuk (Profil → Keamanan), dari tabel sesi (SESSION_DRIVER=database). Id
 * sesi tidak pernah ditulis ke halaman — hanya HMAC-nya dengan APP_KEY.
 */
class Sesi
{
    public static function tersedia(): bool
    {
        return config('session.driver') === 'database';
    }

    public static function kunci(string $id): string
    {
        return substr(hash_hmac('sha256', $id, (string) config('app.key')), 0, 24);
    }

    /* ---- Batas diam: keluar otomatis sesudah sekian menit tanpa aktivitas ---- */

    /** Kunci sesi: [id user, waktu permintaan terakhir yang dihitung sebagai aktivitas]. */
    public const AKTIVITAS = 'aktivitas_terakhir';

    /** Dalam detik; tidak boleh melebihi umur simpan sesi. */
    public static function batasDiam(): int
    {
        return 60 * max(1, min((int) config('simtlhp.sesi.batas_diam', 10), (int) config('session.lifetime', 120)));
    }

    public static function menitDiam(): int
    {
        return intdiv(self::batasDiam(), 60);
    }

    /** Jam sungguhan, bukan tanggal demo. Uji memakai jam Carbon. */
    public static function detikKini(): int
    {
        return app()->runningUnitTests() ? now()->getTimestamp() : time();
    }

    /** Waktu aktivitas terakhir milik user ini; 0 bila belum ada. */
    public static function aktivitasTerakhir(Request $r): int
    {
        [$id, $waktu] = array_pad((array) $r->session()->get(self::AKTIVITAS), 2, 0);

        return $id === $r->user()?->id ? (int) $waktu : 0;
    }

    public static function catatAktivitas(Request $r, int $waktu): void
    {
        $r->session()->put(self::AKTIVITAS, [$r->user()?->id, $waktu]);
    }

    public static function sisaDiam(Request $r): int
    {
        $terakhir = self::aktivitasTerakhir($r);

        return $terakhir ? max(0, self::batasDiam() - (self::detikKini() - $terakhir)) : self::batasDiam();
    }

    /** Halaman tujuan sesudah masuk lagi — hanya alamat di aplikasi ini. */
    public static function alamatKembali(Request $r): ?string
    {
        $jalur = $r->input('kembali');
        if (! is_string($jalur)) {
            $jalur = null;
            if ($r->isMethod('GET') && ! $r->expectsJson()) {
                $jalur = $r->getRequestUri();
            } elseif ($ref = parse_url((string) $r->headers->get('referer'))) {
                if (($ref['host'] ?? null) === $r->getHost()) {
                    $jalur = ($ref['path'] ?? '/').(isset($ref['query']) ? '?'.$ref['query'] : '');
                }
            }
        }
        if (! is_string($jalur) || strlen($jalur) > 2000 || ! str_starts_with($jalur, '/')
            || str_starts_with($jalur, '//') || str_contains($jalur, '\\')) {
            return null;
        }
        $dasar = $r->getBasePath();
        $relatif = $dasar !== '' && str_starts_with($jalur, $dasar.'/') ? substr($jalur, strlen($dasar)) : $jalur;
        if (preg_match('#^/(masuk|keluar|sesi|sso-login)(/|\?|$)#', $relatif)) {
            return null;
        }

        return $r->getSchemeAndHttpHost().$jalur;
    }

    /** Bukan flash: cek lonceng di latar tidak boleh menghabiskan pesannya sebelum halaman masuk tampil. */
    public static function tandaiBerakhir(Request $r, ?string $kembali, ?int $oleh = null): void
    {
        $r->session()->put('sesi_berakhir', true);
        if ($kembali) {
            $r->session()->put('url.intended', $kembali);
        }
        /* Pemilik halaman tujuan: orang lain yang masuk di peramban ini diantar ke beranda. */
        if ($oleh) {
            $r->session()->put('url.intended_oleh', $oleh);
        }
    }

    /** Akhiri sesi yang diam melewati batas; tercatat sebagai keluar otomatis. */
    public static function akhiriKarenaDiam(Request $r, User $u, int $diam): Response
    {
        $kembali = self::alamatKembali($r);
        $lewatSso = (bool) $r->session()->get('masuk_lewat_sso');
        Aktivitas::catat('keluar', 'Keluar otomatis: '.self::menitDiam().' menit tanpa aktivitas', [
            'oleh' => $u, 'rincian' => ['cara' => 'otomatis', 'batas_menit' => self::menitDiam(), 'diam_menit' => intdiv($diam, 60)],
        ]);
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        self::tandaiBerakhir($r, $kembali, $u->id);
        $ke = ($lewatSso ? Penyedia::alamatKeluar() : null) ?: route('masuk');

        return $r->expectsJson()
            ? response()->json(['sesi' => 'berakhir', 'ke' => $ke], 401)
            : redirect()->away($ke);
    }

    /**
     * Sesi akun ini yang masih hidup; yang sedang dipakai paling atas. `detik` memakai jam Laravel
     * (ikut tanggal demo), sama dengan penulis `last_activity`.
     */
    public static function daftar(User $u, Request $r): Collection
    {
        if (! self::tersedia()) {
            return collect();
        }
        $kini = (string) $r->session()->getId();
        $sekarang = now()->getTimestamp();
        $batas = $sekarang - self::batasDiam();

        return self::tabel()->where('user_id', $u->id)->where('last_activity', '>=', $batas)
            ->orderByDesc('last_activity')->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($x) => (object) [
                'kunci' => self::kunci((string) $x->id),
                'ini' => hash_equals($kini, (string) $x->id),
                'perangkat' => Perangkat::sebut($x->user_agent),
                'ip' => $x->ip_address,
                'detik' => max(0, $sekarang - (int) $x->last_activity),
            ])
            ->sortByDesc('ini')->values();
    }

    /**
     * Sesi akun orang lain, dilihat Admin DTI (Pengguna → Detail); tanpa kunci — diakhiri sekaligus
     * lewat Akun::putuskanSesi.
     */
    public static function milik(User $u): Collection
    {
        if (! self::tersedia()) {
            return collect();
        }
        $sekarang = now()->getTimestamp();
        $batas = $sekarang - self::batasDiam();

        return self::tabel()->where('user_id', $u->id)->where('last_activity', '>=', $batas)
            ->orderByDesc('last_activity')->get(['ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($x) => (object) [
                'perangkat' => Perangkat::sebut($x->user_agent),
                'ip' => $x->ip_address,
                'detik' => max(0, $sekarang - (int) $x->last_activity),
                'waktu' => Carbon::createFromTimestamp((int) $x->last_activity),
            ])->values();
    }

    /**
     * Keluarkan satu sesi lain menurut kuncinya, atau "semua"; sesi yang sedang dipakai tidak ikut.
     */
    public static function putus(User $u, Request $r, string $kunci): Collection
    {
        if (! self::tersedia()) {
            return collect();
        }
        $lain = self::tabel()->where('user_id', $u->id)->where('id', '!=', (string) $r->session()->getId())
            ->get(['id', 'user_agent']);
        $kena = $kunci === 'semua' ? $lain : $lain->filter(fn ($x) => hash_equals(self::kunci((string) $x->id), $kunci));
        if ($kena->isNotEmpty()) {
            self::tabel()->whereIn('id', $kena->pluck('id')->all())->delete();
        }

        return $kena->map(fn ($x) => Perangkat::sebut($x->user_agent)['nama'])->values();
    }

    private static function tabel()
    {
        return DB::connection(config('session.connection'))->table(config('session.table', 'sessions'));
    }
}
