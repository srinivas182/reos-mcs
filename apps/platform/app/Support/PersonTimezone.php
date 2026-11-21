<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The time zone a person's screen is in, for "today" on the website. The web
 * app writes the browser's zone to the `reos_tz` cookie; before it has, and
 * for anything that isn't a real zone, REOS uses Texas time (the pilot).
 */
final class PersonTimezone
{
    public const COOKIE = 'reos_tz';

    public const DEFAULT = 'America/Chicago';

    public static function of(Request $request): string
    {
        $zone = $request->cookie(self::COOKIE);

        return is_string($zone) && in_array($zone, timezone_identifiers_list(), true) ? $zone : self::DEFAULT;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} Local midnight to the next. */
    public static function today(Request $request): array
    {
        $start = CarbonImmutable::now(self::of($request))->startOfDay();

        return [$start, $start->addDay()];
    }
}
