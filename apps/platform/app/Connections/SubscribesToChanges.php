<?php

declare(strict_types=1);

namespace App\Connections;

use App\Models\Connection;

/**
 * Drivers whose tool can push "something changed" to REOS's webhook address.
 * A connection may need several channels (one per calendar); each lasts a
 * limited time and is renewed before it ends (`ChangeChannels`).
 */
interface SubscribesToChanges
{
    /**
     * What to watch for this connection now (for calendars, the ones synced).
     *
     * @return list<string>
     */
    public function watchedResources(Connection $connection): array;

    /**
     * Ask the tool to send changes to one resource to `$webhookUrl`.
     *
     * @return array{id: string, resource_id: string|null, expires_at: string|null}
     */
    public function subscribe(Connection $connection, string $accessToken, string $webhookUrl, string $resource): array;

    /** Best effort: stop a channel REOS no longer needs. */
    public function unsubscribe(Connection $connection, string $accessToken, string $channelId, ?string $resourceId): void;
}
