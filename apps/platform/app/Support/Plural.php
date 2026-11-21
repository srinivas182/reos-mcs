<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Plural strings on the server, from the same `key_one` / `key_other`
 * entries the web and phone apps use (i18next style). English and Spanish
 * both use "one" for 1 and "other" for everything else.
 */
final class Plural
{
    /** @param  array<string, string|int|null>  $replace */
    public static function of(string $key, int $count, array $replace = []): string
    {
        $text = __($key.'_'.($count === 1 ? 'one' : 'other'), [...$replace, 'count' => $count]);

        return is_string($text) ? $text : $key;
    }
}
