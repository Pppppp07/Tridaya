<?php

namespace App\Support;

use App\Support\Direktori\DirektoriBerkas;
use App\Support\Direktori\DirektoriHttp;
use App\Support\Direktori\GagalDirektori;
use App\Support\Direktori\SumberPegawai;
use Illuminate\Support\Collection;

/**
 * Direktori pegawai IRM/eHRM — padanan `IRM_CONTOH` prototipe.
 *
 * Kata Bang Kamal, penanggung jawab unit kerja "ketik dari IRM akunnya", "data
 * sama emailnya otomatis", dan "jangan tertukar atributnya". Sejak 27 Sep
 * sumbernya bisa ditukar lewat `SIMTLHP_DIREKTORI` tanpa mengubah pemakainya:
 *
 * - `berkas` (bawaan) — `database/data/irm-contoh.json`, dibangkitkan
 *   `Prototipe/alat/buat-irm-contoh.py`, sama persis dengan prototipe;
 * - `http` — layanan eHRM Pusdatin (App\Support\Direktori\DirektoriHttp).
 *
 * Pemakainya cukup tahu empat hal: mencari pegawai, mengambil satu pegawai
 * menurut NIP, pegawai satu unit kerja, dan pesan error terakhir bila
 * sumbernya tidak bisa ditanya.
 */
class DirektoriIrm
{
    private static ?SumberPegawai $sumber = null;

    private static ?string $error = null;

    public static function sumber(): SumberPegawai
    {
        return self::$sumber ??= match ((string) config('simtlhp.direktori.driver', 'berkas')) {
            'http'  => new DirektoriHttp((array) config('simtlhp.direktori.http')),
            default => new DirektoriBerkas((string) (config('simtlhp.direktori.berkas') ?: config('simtlhp.irm_berkas'))),
        };
    }

    /** @return Collection<int, array{nip:string, nama:string, jabatan:string, unit:string, unitNama:string, email:string, pj:bool}> */
    public static function semua(): Collection
    {
        return self::jaga(fn () => self::sumber()->semua());
    }

    /**
     * Pegawai yang namanya — atau NIP-nya — memuat kata pencari. NIP boleh
     * diketik berkelompok seperti di kartu pegawai. Baru dicari sesudah dua
     * huruf, supaya sumbernya tidak ditanya untuk tiap ketukan pertama.
     */
    public static function cari(string $kata, int $batas = 12): Collection
    {
        $q = trim((string) preg_replace('/\s+/u', ' ', $kata));
        if (mb_strlen($q) < 2) {
            return collect();
        }

        return self::jaga(fn () => self::sumber()->cari($q, $batas));
    }

    public static function nip(?string $nip): ?array
    {
        $nip = (string) preg_replace('/\D/u', '', (string) $nip);

        return $nip === '' ? null : self::jaga(fn () => self::sumber()->nip($nip), null);
    }

    /** Pegawai yang tercatat di unit kerja ini menurut IRM/eHRM. */
    public static function diUnit(string $kodeUnit): Collection
    {
        return self::jaga(fn () => self::sumber()->diUnit($kodeUnit));
    }

    /** Pesan error pertanyaan terakhir, atau null bila lancar. */
    public static function error(): ?string
    {
        return self::$error;
    }

    /** NIP ditulis berkelompok: lahir, TMT, jenis kelamin, nomor urut. */
    public static function nipTampil(?string $nip): string
    {
        return $nip && strlen($nip) === 18
            ? substr($nip, 0, 8).' '.substr($nip, 8, 6).' '.substr($nip, 14, 1).' '.substr($nip, 15)
            : ($nip ?: '—');
    }

    /** Dilepas di TestCase, sama dengan cache statis lain. */
    public static function lupakan(): void
    {
        self::$sumber = null;
        self::$error = null;
    }

    private static function jaga(callable $f, $kosong = 'koleksi')
    {
        try {
            self::$error = null;

            return $f();
        } catch (GagalDirektori $e) {
            self::$error = $e->getMessage();

            return $kosong === 'koleksi' ? collect() : $kosong;
        }
    }
}
