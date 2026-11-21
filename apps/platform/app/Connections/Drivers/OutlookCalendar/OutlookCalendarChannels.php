<?php

declare(strict_types=1);

namespace App\Connections\Drivers\OutlookCalendar;

use Illuminate\Support\Str;

/**
 * Microsoft Graph change notifications carry back the `clientState` REOS set
 * when it subscribed (at most 128 characters). REOS's says which connection
 * it is for and is signed, so a forged notification is refused.
 */
final class OutlookCalendarChannels
{
    public static function clientState(int $connectionId): string
    {
        $state = "reos-{$connectionId}-".Str::lower(Str::random(12));

        return $state.'.'.substr(self::sign($state), 0, 64);
    }

    /** The connection a genuine notification is for, or null. */
    public static function connectionFor(string $clientState): ?int
    {
        $parts = explode('.', $clientState, 2);

        if (count($parts) !== 2 || ! hash_equals(substr(self::sign($parts[0]), 0, 64), $parts[1])) {
            return null;
        }

        return preg_match('/^reos-(\d+)-[a-z0-9]+$/', $parts[0], $match) === 1 ? (int) $match[1] : null;
    }

    private static function sign(string $state): string
    {
        return hash_hmac('sha256', "outlook-calendar-subscription:{$state}", config()->string('app.key'));
    }
}
