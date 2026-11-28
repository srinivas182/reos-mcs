<?php

declare(strict_types=1);

namespace App\Brokerage;

use Carbon\CarbonImmutable;

/**
 * Working hours for the approvals fallback (A15.3): 8 AM to 6 PM, Monday to
 * Friday, in Texas. An item sent on Friday evening hasn't waited the
 * weekend.
 */
final class WorkingHours
{
    public const TIMEZONE = 'America/Chicago';

    private const START = 8;

    private const END = 18;

    /** Working hours between two moments (whole and partial hours). */
    public static function between(CarbonImmutable $from, CarbonImmutable $to): float
    {
        $from = $from->setTimezone(self::TIMEZONE);
        $to = $to->setTimezone(self::TIMEZONE);

        if ($to->lessThanOrEqualTo($from)) {
            return 0.0;
        }

        $minutes = 0;
        $day = $from->startOfDay();

        // At most 60 days back: older items are overdue whatever the count.
        for ($i = 0; $i < 60 && $day->lessThan($to); $i++, $day = $day->addDay()) {
            if ($day->isWeekend()) {
                continue;
            }

            $open = $day->setTime(self::START, 0);
            $close = $day->setTime(self::END, 0);
            $start = $from->greaterThan($open) ? $from : $open;
            $end = $to->lessThan($close) ? $to : $close;

            if ($end->greaterThan($start)) {
                $minutes += (int) $start->diffInMinutes($end, true);
            }
        }

        return round($minutes / 60, 2);
    }
}
