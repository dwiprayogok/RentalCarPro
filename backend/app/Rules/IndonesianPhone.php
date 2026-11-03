<?php

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Dipakai SETELAH nilai dinormalisasi oleh Phone::normalize() di prepareForValidation(). */
class IndonesianPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Phone::isValidIndonesian($value)) {
            $fail('Format :attribute tidak valid. Gunakan nomor HP Indonesia, mis. 0812xxxx atau +62812xxxx.');
        }
    }
}
