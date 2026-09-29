<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Perangkat yang sedang masuk dengan sebuah akun (28 Sep) — Profil →
 * Keamanan. Dibaca dari tabel sesi, jadi hanya ada bila SESSION_DRIVER=
 * database (bawaan aplikasi ini sejak 27 Sep).
 *
 * Id sesi tidak pernah ditulis ke halaman: yang ditampilkan dan dikirim balik
 * hanya kuncinya — HMAC id itu dengan APP_KEY. Tanpa itu, siapa pun yang bisa
 * membaca halamannya bisa mengambil alih sesi di perangkat lain.
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

    /**
     * Sesi akun ini yang masih hidup; yang sedang dipakai paling atas.
     *
     * `detik` = berapa lama tidak dipakai. Dihitung dengan jam yang sama
     * dengan penulis sesinya — Laravel mencatat `last_activity` dari
     * Carbon::now(), yang ikut tanggal demo (SIMTLHP_HARI_INI). Memakai
     * time() di sini membuat semua sesi tampak kedaluwarsa di demo.
     *
     * @return Collection<int, object{kunci:string, ini:bool, perangkat:array{nama:string, ponsel:bool}, ip:?string, detik:int}>
     */
    public static function daftar(User $u, Request $r): Collection
    {
        if (! self::tersedia()) {
            return collect();
        }
        $kini = (string) $r->session()->getId();
        $sekarang = now()->getTimestamp();
        $batas = $sekarang - 60 * (int) config('session.lifetime', 120);

        return self::tabel()->where('user_id', $u->id)->where('last_activity', '>=', $batas)
            ->orderByDesc('last_activity')->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($x) => (object) [
                'kunci'     => self::kunci((string) $x->id),
                'ini'       => hash_equals($kini, (string) $x->id),
                'perangkat' => Perangkat::sebut($x->user_agent),
                'ip'        => $x->ip_address,
                'detik'     => max(0, $sekarang - (int) $x->last_activity),
            ])
            ->sortByDesc('ini')->values();
    }

    /**
     * Keluarkan satu sesi lain menurut kuncinya, atau semuanya ("semua").
     * Sesi yang sedang dipakai tidak pernah ikut.
     *
     * @return Collection<int, string> nama perangkat yang dikeluarkan
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
