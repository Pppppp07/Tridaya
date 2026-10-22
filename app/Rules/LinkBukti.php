<?php

namespace App\Rules;

use App\Support\KebijakanBukti;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class LinkBukti implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }
        $hasil = KebijakanBukti::periksa(is_string($value) ? $value : null);
        if (! $hasil['boleh']) {
            KebijakanBukti::catatPenolakan(is_string($value) ? $value : '', $hasil);
            $fail($hasil['alasan']);
        }
    }
}
