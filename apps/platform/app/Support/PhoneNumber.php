<?php

declare(strict_types=1);

namespace App\Support;

/** Phone numbers as people type them, made E.164 (+18325550123). US numbers may skip the +1. */
final class PhoneNumber
{
    public static function normalise(string $typed): ?string
    {
        $typed = trim($typed);
        $digits = (string) preg_replace('/\D+/', '', $typed);

        if (str_starts_with($typed, '+')) {
            return strlen($digits) >= 8 && strlen($digits) <= 15 && $digits[0] !== '0' ? '+'.$digits : null;
        }

        return match (true) {
            strlen($digits) === 10 && $digits[0] >= '2' => '+1'.$digits,
            strlen($digits) === 11 && $digits[0] === '1' => '+'.$digits,
            default => null,
        };
    }

    /** +1 832-555-0123 → •••• 0123, for screens and logs. */
    public static function mask(string $phone): string
    {
        return '•••• '.substr($phone, -4);
    }
}
