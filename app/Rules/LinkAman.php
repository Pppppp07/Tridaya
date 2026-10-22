<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Link berkas hanya http(s): link disimpan di `href` dan dibuka orang lain, jadi `javascript:`
 * harus ditolak. Kosong boleh.
 */
class LinkAman implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }
        if (! self::sah((string) $value)) {
            $fail('Link harus berupa alamat web lengkap, diawali https:// atau http://.');
        }
    }

    /** Dipakai juga saat menggambar: link lama yang tidak sah tidak dijadikan `href`. */
    public static function sah(?string $link): bool
    {
        $t = trim((string) $link);
        if ($t === '' || strlen($t) > 2000 || preg_match('/[\x00-\x1F\x7F\s]/', $t)) {
            return false;
        }
        $skema = strtolower((string) parse_url($t, PHP_URL_SCHEME));

        return in_array($skema, ['http', 'https'], true)
            && filter_var($t, FILTER_VALIDATE_URL) !== false
            && parse_url($t, PHP_URL_USER) === null && parse_url($t, PHP_URL_PASS) === null
            && (string) parse_url($t, PHP_URL_HOST) !== '';
    }
}
