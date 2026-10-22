<?php

namespace App\Support;

use App\Models\Laporan;
use App\Models\LogAktivitas;
use App\Models\Rekomendasi;
use App\Models\Satker;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Penulis tunggal log aktivitas: siapa (nama, peran, unit saat itu), kapan, apa yang diubah
 * (sebelum → sesudah), dari alamat dan perangkat mana. Masuk lewat Jejak::riwayat(), pengendali
 * Data master/Pengguna/masuk-keluar, dan CatatAktivitas sebagai jaring terakhir. Password dan token
 * tidak pernah dicatat.
 */
class Aktivitas
{
    /** Audit memakai waktu nyata; tanggal contoh hanya untuk simulasi berkas. */
    public static function waktuKini(): Carbon
    {
        return app()->runningUnitTests()
            ? now()
            : Carbon::instance(new \DateTimeImmutable('now', new \DateTimeZone(config('app.timezone', 'UTC'))));
    }

    /** Kelompok, untuk filter di layar Log aktivitas. */
    public const KELOMPOK = [
        'akses' => 'Masuk & akses',
        'berkas' => 'Berkas tindak lanjut',
        'laporan' => 'Laporan',
        'master' => 'Data Master',
        'pengguna' => 'User & akses',
        /* Pengaturan akun sendiri. */
        'akun' => 'Pengaturan akun',
    ];

    /** Tanda di permintaan: sudah ada yang mencatat, jaring terakhir tidak perlu. */
    private const TANDA = 'aktivitas.tercatat';

    public static function catat(string $aksi, string $ringkasan, array $opsi = []): LogAktivitas
    {
        $u = array_key_exists('oleh', $opsi) ? $opsi['oleh'] : auth()->user();
        $subjek = $opsi['subjek'] ?? null;
        $req = app()->bound('request') ? request() : null;
        /* Tes HTTP juga berjalan di console: adanya rute membedakan request sungguhan dari
           perintah artisan. */
        $server = app()->runningInConsole() && ! $req?->route();
        $rincian = $opsi['rincian'] ?? [];
        $rincian['konteks'] = $server
            ? ['sumber' => 'perintah_server']
            : array_filter([
                'sumber' => 'web', 'metode' => $req?->method(),
                'alamat' => $req ? '/'.ltrim($req->path(), '/') : null,
                'rute' => $req?->route()?->getName(),
            ]);

        $log = LogAktivitas::create([
            'waktu' => $opsi['waktu'] ?? self::waktuKini(),
            'user_id' => $u?->id,
            'nama' => Str::limit((string) ($u?->name ?? $opsi['nama'] ?? ''), 147, '…') ?: null,
            'peran' => $u?->peranAktif()->value,
            'satker_id' => $u?->satker_id,
            'aksi' => Str::limit($aksi, 60, ''),
            'kelompok' => $opsi['kelompok'] ?? self::kelompokDari($aksi),
            'ringkasan' => Str::limit($ringkasan, 497, '…'),
            'subjek_tipe' => $subjek ? self::tipe($subjek) : null,
            'subjek_id' => $subjek?->getKey(),
            'rincian' => $rincian,
            /* Perintah server (artisan, jadwal) tidak punya alamat — kosong. */
            'ip' => $server ? null : $req?->ip(),
            'agen' => ! $server && $req?->userAgent() ? Str::limit((string) $req->userAgent(), 252, '…') : null,
        ]);

        $req?->attributes->set(self::TANDA, true);
        /* Data berubah → hitungan bingkai di cache basi. Masuk, keluar, dan pengaturan akun
           tidak mengubah data bersama. */
        if (! in_array($log->kelompok, ['akses', 'akun'], true)) {
            Rangka::dataBerubah();
        }

        /* Alert hanya alat bantu: kegagalannya tidak boleh menggagalkan perubahan dan log
           aslinya. */
        try {
            AlertKeamanan::proses($log);
        } catch (\Throwable $e) {
            report($e);
        }
        /* Perubahan sistem diberitahukan ke Admin DTI lain; sama, tidak boleh menggagalkan. */
        try {
            Pemberitahuan::perubahanSistem($log, $u);
        } catch (\Throwable $e) {
            report($e);
        }

        return $log;
    }

    /**
     * Sebutan dan alamat objek tiap baris log, dimuat sekaligus. `$linkAkun` palsu = akun tidak
     * ditautkan ke Log aktivitas.
     */
    public static function subjek(Collection $baris, bool $linkAkun = true): array
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
        foreach (Satker::whereIn('id', $id('satker'))->get() as $x) {
            $h['satker:'.$x->id] = ['teks' => $x->namaPendek(), 'url' => null];
        }
        if ($id('alert_keamanan')->isNotEmpty()) {
            foreach (\App\Models\AlertKeamanan::whereIn('id', $id('alert_keamanan'))->get() as $x) {
                $h['alert_keamanan:'.$x->id] = ['teks' => 'Alert #'.$x->id, 'url' => $linkAkun ? route('log', ['tab' => 'alert', 'alert' => $x->id]) : null];
            }
        }

        return $h;
    }

    public static function sudahTercatat(Request $req): bool
    {
        return (bool) $req->attributes->get(self::TANDA, false);
    }

    /** Kelompok menurut awalan kunci aksinya. */
    public static function kelompokDari(string $aksi): string
    {
        return match (true) {
            str_starts_with($aksi, 'masuk'), str_starts_with($aksi, 'keluar'),
            str_starts_with($aksi, 'akses') => 'akses',
            str_starts_with($aksi, 'akun') => 'akun',
            str_starts_with($aksi, 'master.pengguna') => 'pengguna',
            str_starts_with($aksi, 'master.unit.pj') => 'pengguna',
            str_starts_with($aksi, 'master') => 'master',
            str_starts_with($aksi, 'laporan') => 'laporan',
            default => 'berkas',
        };
    }

    /** Sebelum → sesudah, hanya medan yang berubah. */
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
            $m instanceof Laporan => 'laporan',
            default => Str::snake(class_basename($m)),
        };
    }
}
