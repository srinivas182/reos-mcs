<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Languages REOS is available in. Matches `supportedLocales` in packages/i18n.
 */
enum Locale: string
{
    case English = 'en';
    case Spanish = 'es';

    /** Picks a supported language from a tag like `es-MX`; null if none matches. */
    public static function fromTag(?string $tag): ?self
    {
        if ($tag === null || $tag === '') {
            return null;
        }

        return self::tryFrom(Str::of($tag)->lower()->before('-')->before('_')->value());
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $locale): string => $locale->value, self::cases());
    }
}
