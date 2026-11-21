<?php

declare(strict_types=1);

namespace App\Connections;

use Carbon\CarbonImmutable;

/** How far back the first sync reaches: 3 or 12 months, or everything. */
enum HistoryRange: string
{
    case ThreeMonths = '3m';
    case TwelveMonths = '12m';
    case Everything = 'all';

    public const DEFAULT = self::ThreeMonths;

    /** The oldest date to fetch, or null for everything. */
    public function since(): ?CarbonImmutable
    {
        return match ($this) {
            self::ThreeMonths => CarbonImmutable::now()->subMonthsNoOverflow(3)->startOfDay(),
            self::TwelveMonths => CarbonImmutable::now()->subMonthsNoOverflow(12)->startOfDay(),
            self::Everything => null,
        };
    }
}
