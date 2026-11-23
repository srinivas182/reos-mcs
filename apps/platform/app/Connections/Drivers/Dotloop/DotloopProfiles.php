<?php

declare(strict_types=1);

namespace App\Connections\Drivers\Dotloop;

use App\Models\Connection;

/**
 * Which dotloop profiles' loops sync (B9.2): each profile can be chosen on
 * the connection's settings; until then, all of them.
 */
final class DotloopProfiles
{
    /** @param  list<array{id: string, name: string}>  $profiles */
    public static function remember(Connection $connection, array $profiles): void
    {
        if (($connection->meta['profiles'] ?? null) !== $profiles) {
            $connection->forceFill(['meta' => [...$connection->meta, 'profiles' => $profiles]])->save();
        }
    }

    /** @return list<array{id: string, name: string}> */
    public static function known(Connection $connection): array
    {
        $out = [];
        foreach ((array) ($connection->meta['profiles'] ?? []) as $p) {
            if (is_array($p) && is_string($p['id'] ?? null)) {
                $out[] = ['id' => $p['id'], 'name' => is_string($p['name'] ?? null) ? $p['name'] : $p['id']];
            }
        }

        return $out;
    }

    /** @return list<string> */
    public static function chosen(Connection $connection): array
    {
        $known = array_column(self::known($connection), 'id');
        $picked = $connection->settings['profiles'] ?? null;

        return $picked === null ? $known : array_values(array_intersect($known, $picked));
    }
}
