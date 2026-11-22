<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The private preview for usability checks (P7.0). Pilot users can't read
 * the server log, so the simulators use one known code there, and every
 * screen says it's a preview with test data.
 */
final class Preview
{
    public static function on(): bool
    {
        return config('reos.preview') === true && ! app()->isProduction();
    }

    public static function code(): string
    {
        $code = config('reos.preview_code');

        return is_string($code) ? $code : '123456';
    }
}
