<?php

namespace App\Support;

use App\Models\LogAktivitas;

/** Membaca bukti yang disimpan, tanpa mengubah log atau menebak niat pelaku. */
final class TampilanLog
{
    public const KET_HASIL = [
        'Tercatat: aktivitas tersimpan di log; bukan tanda proses tindak lanjut sudah selesai.',
        'Ditolak: login, akses, atau link tidak diizinkan. Dibatasi: akun atau permintaan terkena pembatasan.',
        'Gagal: tindakan tidak berhasil. Hasil log ini tidak menentukan apakah aktivitas merupakan ancaman.',
    ];

    /** Frasa template lama dirapikan; nama dan isi bukti bebas tetap asli. */
    public static function teksUi(string $teks): string
    {
        return preg_replace_callback('/\b(perintah\s+peladen|menurut\s+surel)\b/iu', static function ($cocok) {
            $asli = $cocok[0];
            $frasa = preg_replace('/\s+/', ' ', mb_strtolower($asli));
            $baru = ['perintah peladen' => 'perintah server', 'menurut surel' => 'menurut email'][$frasa];

            return $asli === mb_strtoupper($asli) ? mb_strtoupper($baru)
                : ($asli[0] === strtoupper($asli[0]) ? ucfirst($baru) : $baru);
        }, $teks) ?? $teks;
    }

    public static function ringkasan(LogAktivitas $log): string
    {
        return self::teksUi($log->ringkasan);
    }

    /** Hanya label actor sistem yang dikenal; nama user tidak diterjemahkan. */
    public static function nama(LogAktivitas $log): ?string
    {
        return ! $log->user_id && ! $log->peran && $log->nama === 'Perintah peladen' ? 'Perintah server' : $log->nama;
    }

    /**
     * Varian kata UI agar audit lama tetap bisa dicari (maks. empat). Batas kata mencegah
     * "Mailserver" ikut diterjemahkan.
     */
    public static function varianCari(string $teks): array
    {
        $varian = [$teks];
        foreach (['server' => 'peladen', 'email' => 'surel'] as $baru => $lama) {
            foreach ($varian as $v) {
                $alias = preg_replace_callback('/(?<![\p{L}\p{N}_])'.$baru.'(?![\p{L}\p{N}_])/iu', static function ($cocok) use ($lama) {
                    $asli = $cocok[0];

                    return $asli === mb_strtoupper($asli) ? mb_strtoupper($lama)
                        : ($asli[0] === strtoupper($asli[0]) ? ucfirst($lama) : $lama);
                }, $v) ?? $v;
                if (! in_array($alias, $varian, true)) {
                    $varian[] = $alias;
                }
            }
        }

        return $varian;
    }

    /** @return array{nama:string,kelas:string} */
    public static function hasil(LogAktivitas $log): array
    {
        $http = self::permintaan($log)['status'];
        $kunci = match (true) {
            in_array($log->aksi, ['masuk.dikunci', 'akses.akun_nonaktif'], true), $http === 429 => 'dibatasi',
            in_array($log->aksi, ['akses.ditolak', 'masuk.sso.ditolak', 'bukti.link.ditolak'], true),
            in_array($http, [401, 403], true) => 'ditolak',
            $log->aksi === 'masuk.gagal', ($http !== null && $http >= 400) => 'gagal',
            default => 'tercatat',
        };

        return ['nama' => ['dibatasi' => 'Dibatasi', 'ditolak' => 'Ditolak', 'gagal' => 'Gagal', 'tercatat' => 'Tercatat'][$kunci], 'kelas' => $kunci];
    }

    public static function asal(LogAktivitas $log): string
    {
        $konteks = (array) ($log->rincian['konteks'] ?? []);
        if (($konteks['sumber'] ?? null) === 'web') {
            return 'Permintaan web';
        }
        if (($konteks['sumber'] ?? null) === 'perintah_server') {
            return 'Perintah server';
        }
        $permintaan = self::permintaan($log);
        if ($permintaan['metode'] && $permintaan['alamat']) {
            return 'Permintaan web';
        }
        /* Log lama perintah artisan menulis identitas ini; IP kosong saja bukan bukti kegiatan
           dari CLI. */
        if (in_array($log->nama, ['Perintah server', 'Perintah peladen'], true)
            || str_contains($log->ringkasan, 'lewat perintah server')
            || str_contains($log->ringkasan, 'lewat perintah peladen')) {
            return 'Perintah server';
        }

        return 'Log lama — asal tidak tercatat';
    }

    /** @return array{metode:?string,alamat:?string,rute:?string,status:?int} */
    public static function permintaan(LogAktivitas $log): array
    {
        $r = (array) $log->rincian;
        $k = (array) ($r['konteks'] ?? []);
        $teks = static fn ($v) => is_string($v) && $v !== '' ? $v : null;
        $status = $r['kode_http'] ?? $r['code_http'] ?? $k['kode_http'] ?? null;

        return [
            'metode' => $teks($k['metode'] ?? $r['metode'] ?? null),
            'alamat' => $teks($k['alamat'] ?? $r['alamat'] ?? null),
            'rute' => $teks($k['rute'] ?? $r['rute'] ?? null),
            'status' => is_numeric($status) && (int) $status >= 100 && (int) $status <= 599 ? (int) $status : null,
        ];
    }

    /** Hanya salinan ekspor yang dinetralkan; nilai asli di log tetap utuh. */
    public static function selCsv(mixed $nilai): string
    {
        $teks = (string) ($nilai ?? '');

        return preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $teks) || str_starts_with($teks, "\t") || str_starts_with($teks, "\r")
            ? "'".$teks : $teks;
    }
}
