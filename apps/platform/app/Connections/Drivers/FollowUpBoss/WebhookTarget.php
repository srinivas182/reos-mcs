<?php

declare(strict_types=1);

namespace App\Connections\Drivers\FollowUpBoss;

use App\Models\Connection;

/**
 * Follow Up Boss and Lofty call one webhook address for every account, so
 * REOS adds the connection to it, signed (`?c=12.abc…`), when it subscribes;
 * a forged or changed id is refused. Each tool signs with its own purpose,
 * so one tool's address can't be used for another. Deleted people named by a
 * webhook are kept on the connection until the next people sync removes them.
 */
final class WebhookTarget
{
    public static function seal(int $connectionId, string $purpose = 'fub-webhook'): string
    {
        return $connectionId.'.'.self::sign($connectionId, $purpose);
    }

    public static function open(mixed $value, string $purpose = 'fub-webhook'): ?int
    {
        if (! is_string($value) || preg_match('/^(\d+)\.([a-f0-9]{32})$/', $value, $m) !== 1) {
            return null;
        }

        return hash_equals(self::sign((int) $m[1], $purpose), $m[2]) ? (int) $m[1] : null;
    }

    /** @param  array<array-key, mixed>  $ids */
    public static function remember(int $connectionId, array $ids): void
    {
        $connection = Connection::query()->find($connectionId);

        if ($connection === null) {
            return;
        }

        $known = array_values(array_filter((array) ($connection->meta['deleted_people'] ?? []), is_string(...)));
        $add = array_values(array_filter(array_map(static fn (mixed $id): ?string => is_scalar($id) ? (string) $id : null, $ids)));
        $connection->forceFill(['meta' => [...$connection->meta, 'deleted_people' => array_values(array_unique([...$known, ...$add]))]])->save();
    }

    private static function sign(int $id, string $purpose): string
    {
        return substr(hash_hmac('sha256', "{$purpose}:{$id}", config()->string('app.key')), 0, 32);
    }
}
