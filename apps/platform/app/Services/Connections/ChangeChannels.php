<?php

declare(strict_types=1);

namespace App\Services\Connections;

use App\Connections\ConnectorDriver;
use App\Connections\SubscribesToChanges;
use App\Models\Connection;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Push channels for a connection (ADR 0013, B4.1): one per watched resource
 * (for calendars, one per synced calendar), kept in `meta.channels`.
 *
 * - Missing channels are opened; channels for resources no longer watched
 *   are stopped.
 * - A channel ending within a day is renewed: the new one first, then the
 *   old one stopped, so no change is missed in between.
 * - Only with a public HTTPS address (tools won't call localhost); without
 *   one, the 15-minute schedule keeps data fresh. Push is a bonus: failures
 *   are ignored and tried again next time.
 */
final readonly class ChangeChannels
{
    private const RENEW_WITHIN_HOURS = 24;

    public function __construct(private ConnectionTokens $tokens) {}

    public function keep(Connection $connection, ConnectorDriver $driver): void
    {
        $webhookUrl = url("webhooks/{$connection->integration_id}");

        if (! $driver instanceof SubscribesToChanges || ! str_starts_with($webhookUrl, 'https://')) {
            return;
        }

        $wanted = $driver->watchedResources($connection);
        $kept = [];
        $stale = [];

        foreach ($this->channels($connection) as $channel) {
            $ending = $channel['expires_at'] !== null && Carbon::parse($channel['expires_at'])->lte(Carbon::now()->addHours(self::RENEW_WITHIN_HOURS));

            if (! in_array($channel['resource'], $wanted, true) || $ending || isset($kept[$channel['resource']])) {
                $stale[] = $channel;
            } else {
                $kept[$channel['resource']] = $channel;
            }
        }

        $missing = array_diff($wanted, array_keys($kept));

        if ($missing === [] && $stale === []) {
            return;
        }

        try {
            $token = $this->tokens->accessToken($connection, $driver);
        } catch (Throwable) {
            return;
        }

        foreach ($missing as $resource) {
            try {
                $kept[$resource] = ['resource' => $resource, ...$driver->subscribe($connection, $token, $webhookUrl, $resource)];
            } catch (Throwable) {
                // Tried again after the next sync.
            }
        }

        foreach ($stale as $channel) {
            // Still needed and not renewed: keep the old one until it ends.
            if (in_array($channel['resource'], $wanted, true) && ! isset($kept[$channel['resource']])) {
                $kept[$channel['resource']] = $channel;

                continue;
            }

            try {
                $driver->unsubscribe($connection, $token, $channel['id'], $channel['resource_id']);
            } catch (Throwable) {
                // It ends on its own.
            }
        }

        $meta = array_diff_key($connection->meta, array_flip(['channel_id', 'resource_id', 'channel_expires_at']));
        $connection->forceFill(['meta' => [...$meta, 'channels' => array_values($kept)]])->save();
    }

    /** @return list<array{resource: string, id: string, resource_id: string|null, expires_at: string|null}> */
    private function channels(Connection $connection): array
    {
        $channels = [];

        foreach ((array) ($connection->meta['channels'] ?? []) as $row) {
            if (is_array($row) && is_string($row['resource'] ?? null) && is_string($row['id'] ?? null)) {
                $channels[] = [
                    'resource' => $row['resource'],
                    'id' => $row['id'],
                    'resource_id' => is_string($row['resource_id'] ?? null) ? $row['resource_id'] : null,
                    'expires_at' => is_string($row['expires_at'] ?? null) ? $row['expires_at'] : null,
                ];
            }
        }

        return $channels;
    }
}
