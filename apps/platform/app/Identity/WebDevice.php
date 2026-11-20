<?php

declare(strict_types=1);

namespace App\Identity;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * A browser as a device: a long-lived cookie identifies it (so "new device"
 * emails don't repeat), and the browser and system name it in "Your devices".
 */
final class WebDevice
{
    public const COOKIE = 'reos_device';

    public static function from(Request $request): DeviceInfo
    {
        $id = $request->cookie(self::COOKIE);

        if (! is_string($id) || ! Str::isUuid($id)) {
            $id = (string) Str::uuid();
            Cookie::queue(self::COOKIE, $id, 60 * 24 * 400, httpOnly: true, sameSite: 'lax');
        }

        return new DeviceInfo($id, self::name((string) $request->userAgent()), 'web');
    }

    /** "Chrome · macOS", from the user agent. Names only, no versions. */
    public static function name(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') || str_contains($userAgent, 'CriOS/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        $system = match (true) {
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Mac OS X') => 'macOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'CrOS') => 'ChromeOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        return $system === null ? $browser : "{$browser} · {$system}";
    }
}
