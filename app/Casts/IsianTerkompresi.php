<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Isian draft historis disimpan terkompresi (gzip + base64 di dalam JSON). Satu lembar pemantauan
 * berisi ratusan baris bertekst panjang; JSON mentahnya bisa melewati max_allowed_packet server
 * MySQL/MariaDB (bawaan XAMPP 1 MB). `jumlah` tetap bisa dibaca SQL untuk daftar draft.
 */
class IsianTerkompresi implements CastsAttributes
{
    public function get($model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }
        $isi = json_decode($value, true);
        // Draft lama tersimpan sebagai JSON biasa.
        if (is_array($isi) && isset($isi['gz'])) {
            $isi = json_decode((string) gzdecode(base64_decode($isi['gz'])), true);
        }

        return is_array($isi) ? $isi : null;
    }

    public function set($model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);

        return [$key => json_encode(['jumlah' => count($value['baris'] ?? []), 'gz' => base64_encode(gzencode($json, 6))])];
    }
}
