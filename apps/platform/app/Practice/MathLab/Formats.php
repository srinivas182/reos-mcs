<?php

declare(strict_types=1);

namespace App\Practice\MathLab;

/** Numbers as a problem shows them. */
final class Formats
{
    public static function money(float $value): string
    {
        return '$'.number_format($value, fmod($value, 1.0) === 0.0 ? 0 : 2);
    }

    public static function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.').'%';
    }

    /** Thousands separated, and decimals only when there are some: 1,250 or 1.5. */
    public static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.');
    }
}
