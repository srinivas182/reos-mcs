<?php

declare(strict_types=1);

namespace App\Connections\Drivers\GoogleCalendar;

use Illuminate\Support\Str;

/**
 * Google push channels: REOS names each channel after the connection and
 * signs it, and Google echoes the signature back in `X-Goog-Channel-Token`.
 * A delivery whose token doesn't match is refused.
 */
final class GoogleCalendarChannels
{
    public static function newChannelId(int $connectionId): string
    {
        return "reos-{$connectionId}-".Str::lower(Str::random(12));
    }

    public static function token(string $channelId): string
    {
        return hash_hmac('sha256', "google-calendar-channel:{$channelId}", self::key());
    }

    /** The connection a genuine delivery is for, or null. */
    public static function connectionFor(string $channelId, string $token): ?int
    {
        if (! hash_equals(self::token($channelId), $token)) {
            return null;
        }

        return preg_match('/^reos-(\d+)-[a-z0-9]+$/', $channelId, $match) === 1 ? (int) $match[1] : null;
    }

    private static function key(): string
    {
        return config()->string('app.key');
    }
}
