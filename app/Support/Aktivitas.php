<?php

namespace App\Support;

use App\Models\Laporan;
use App\Models\LogAktivitas;
use App\Models\Rekomendasi;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Penulis tunggal log aktivitas (27 Sep) — padanan `catatLog` prototipe.
 *
 * Bang Kamal: "setiap aktivitas terekam … DTI itu hanya untuk melihat jika dia
 * merubah data, merusak data, kita tinggal nembak siapa pelakunya." Jadi yang
 * dicatat: siapa (nama, peran, dan unitnya SAAT itu), kapan, apa yang
 * diubahnya — kalimat untuk dibaca orang, plus sebelum → sesudah untuk
 * perubahan data master dan hak akses — dari alamat mana dan perangkat apa.
 *
 * Jalan masuknya ada tiga, supaya tidak ada perubahan yang lolos:
 * 1. `Jejak::riwayat()` — setiap gerak berkas tindak lanjut ikut tercatat di
 *    sini dengan kalimat riwayatnya sendiri;
 * 2. pengendali Data master, Pengguna, dan masuk/keluar memanggilnya
 *    langsung, lengkap dengan sebelum → sesudah;
 * 3. middleware `CatatAktivitas` menjadi jaring terakhir: kiriman berhasil
 *    yang belum tercatat lewat dua jalan di atas, dan setiap penolakan 403.
 *
 * Password dan token tidak pernah masuk ke sini.
 */
class Aktivitas
{
    /** Kelompok, untuk filter di layar Log aktivitas. */
    public const KELOMPOK = [
        'akses'    => 'Masuk & akses',
        'berkas'   => 'Berkas tindak lanjut',
        'laporan'  => 'Laporan',
        'master'   => 'Data master',
        'pengguna' => 'Pengguna & hak akses',
        /* Password, perangkat, dan pengaturan akun sendiri (28 Sep). */
        'akun'     => 'Pengaturan akun',
    ];

    /** Tanda di permintaan: sudah ada yang mencatat, jaring terakhir tidak perlu. */
    private const TANDA = 'aktivitas.tercatat';

    /**
     * @param  array{subjek?:Model|null, rincian?:array, oleh?:User|null, nama?:string|null, kelompok?:string|null, waktu?:mixed}  $opsi
     */
    public static function catat(string $aksi, string $ringkasan, array $opsi = []): LogAktivitas
    {
        $u = array_key_exists('oleh', $opsi) ? $opsi['oleh'] : auth()->user();
        $subjek = $opsi['subjek'] ?? null;
        $req = app()->bound('request') ? request() : null;

        $log = LogAktivitas::create([
            'waktu'       => $opsi['waktu'] ?? now(),
            'user_id'     => $u?->id,
            'nama'        => Str::limit((string) ($u?->name ?? $opsi['nama'] ?? ''), 147, '…') ?: null,
            'peran'       => $u?->peran?->value,
            'satker_id'   => $u?->satker_id,
            'aksi'        => Str::limit($aksi, 60, ''),
            'kelompok'    => $opsi['kelompok'] ?? self::kelompokDari($aksi),
            'ringkasan'   => Str::limit($ringkasan, 497, '…'),
            'subjek_tipe' => $subjek ? self::tipe($subjek) : null,
            'subjek_id'   => $subjek?->getKey(),
            'rincian'     => ! empty($opsi['rincian']) ? $opsi['rincian'] : null,
            /* Perintah server (artisan, jadwal) tidak punya alamat — kosong. */
            'ip'          => $req?->ip(),
            'agen'        => $req?->userAgent() ? Str::limit((string) $req->userAgent(), 252, '…') : null,
        ]);

        $req?->attributes->set(self::TANDA, true);
        /* Data berubah — hitungan bingkai yang tersimpan di cache jadi basi.
           Masuk, keluar, penolakan, dan pengaturan akun sendiri tidak mengubah
           data bersama. */
        if (! in_array($log->kelompok, ['akses', 'akun'], true)) {
            Rangka::dataBerubah();
        }

        return $log;
    }

    /**
     * Sebutan dan alamat objek tiap baris log — rekomendasi, laporan, akun,
     * atau unit kerja — dimuat sekaligus, bukan satu per baris. Dipakai Log
     * aktivitas dan Profil → Aktivitas saya (28 Sep). `$linkAkun` palsu: akun
     * tidak dihubungkan ke Log aktivitas, yang hanya dibuka DTI dan Admin.
     *
     * @return array<string, array{teks:string, url:?string}>
     */
    public static function subjek(\Illuminate\Support\Collection $baris, bool $linkAkun = true): array
    {
        $h = [];
        $id = fn (string $t) => $baris->where('subjek_tipe', $t)->pluck('subjek_id')->unique()->filter()->values();
        foreach (Rekomendasi::with('temuan.laporan')->whereIn('id', $id('rekomendasi'))->get() as $x) {
            $h['rekomendasi:'.$x->id] = ['teks' => 'Rekomendasi '.$x->refLhp(), 'url' => route('rekomendasi.show', $x)];
        }
        foreach (Laporan::whereIn('id', $id('laporan'))->get() as $x) {
            $h['laporan:'.$x->id] = ['teks' => 'Laporan '.$x->nomor, 'url' => route('laporan.show', $x)];
        }
        foreach (User::whereIn('id', $id('user'))->get() as $x) {
            $h['user:'.$x->id] = ['teks' => 'Akun '.$x->name, 'url' => $linkAkun ? route('log', ['akun' => $x->id]) : null];
        }
        foreach (\App\Models\Satker::whereIn('id', $id('satker'))->get() as $x) {
            $h['satker:'.$x->id] = ['teks' => $x->namaPendek(), 'url' => null];
        }

        return $h;
    }

    public static function sudahTercatat(\Illuminate\Http\Request $req): bool
    {
        return (bool) $req->attributes->get(self::TANDA, false);
    }

    /** Kelompok menurut awalan kunci aksinya. */
    public static function kelompokDari(string $aksi): string
    {
        return match (true) {
            str_starts_with($aksi, 'masuk'), str_starts_with($aksi, 'keluar'),
            str_starts_with($aksi, 'akses')             => 'akses',
            str_starts_with($aksi, 'akun')              => 'akun',
            str_starts_with($aksi, 'master.pengguna')   => 'pengguna',
            str_starts_with($aksi, 'master.unit.pj')    => 'pengguna',
            str_starts_with($aksi, 'master')            => 'master',
            str_starts_with($aksi, 'laporan')           => 'laporan',
            default                                     => 'berkas',
        };
    }

    /**
     * Sebelum → sesudah, hanya medan yang berubah. Nilai boolean ditulis
     * "aktif"/"nonaktif" dan sejenisnya oleh pemanggil — di sini apa adanya.
     *
     * @return array{sebelum:array, sesudah:array}|array{}
     */
    public static function beda(array $sebelum, array $sesudah): array
    {
        $s = [];
        $d = [];
        foreach ($sesudah as $k => $v) {
            if (($sebelum[$k] ?? null) !== $v) {
                $s[$k] = $sebelum[$k] ?? null;
                $d[$k] = $v;
            }
        }

        return $d ? ['sebelum' => $s, 'sesudah' => $d] : [];
    }

    /** Nama pendek jenis subjek, disimpan di `subjek_tipe`. */
    private static function tipe(Model $m): string
    {
        return match (true) {
            $m instanceof Rekomendasi => 'rekomendasi',
            $m instanceof Laporan     => 'laporan',
            default                   => Str::snake(class_basename($m)),
        };
    }
}
