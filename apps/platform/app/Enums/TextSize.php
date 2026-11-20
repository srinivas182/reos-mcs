<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Text size a person chooses (Standard, Large, Largest). Matches `textScale` in
 * packages/tokens; the web app scales every rem-based size via `<html data-text-size>`.
 */
enum TextSize: string
{
    case Standard = 'standard';
    case Large = 'large';
    case Largest = 'largest';

    public static function fromCookie(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::Standard) : self::Standard;
    }
}
