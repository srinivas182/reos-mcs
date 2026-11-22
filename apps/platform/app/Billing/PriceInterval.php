<?php

declare(strict_types=1);

namespace App\Billing;

/** Paid once (flat fee) or every month or year (subscription). */
enum PriceInterval: string
{
    case Once = 'once';
    case Month = 'month';
    case Year = 'year';

    public function recurring(): bool
    {
        return $this !== self::Once;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $interval): string => $interval->value, self::cases());
    }
}
