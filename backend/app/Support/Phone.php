<?php

namespace App\Support;

/**
 * Normalisasi nomor HP Indonesia ke format E.164 (+62...).
 * Menerima: 0812..., 62812..., +62 812-..., 812... (spasi/tanda hubung diabaikan).
 */
final class Phone
{
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $raw = trim($input);
        if ($raw === '') {
            return null;
        }

        $hasPlus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D/', '', $raw);

        if ($hasPlus) {
            return '+'.$digits;
        }
        if (str_starts_with($digits, '62')) {
            return '+'.$digits;
        }
        if (str_starts_with($digits, '0')) {
            return '+62'.substr($digits, 1);
        }
        if (str_starts_with($digits, '8')) {
            return '+62'.$digits;
        }

        return $digits === '' ? null : '+'.$digits;
    }

    /** Nomor HP Indonesia valid: +62 diikuti 8-9 lalu total 9-13 digit setelah kode negara. */
    public static function isValidIndonesian(?string $normalized): bool
    {
        return $normalized !== null && preg_match('/^\+628\d{7,12}$/', $normalized) === 1;
    }
}
