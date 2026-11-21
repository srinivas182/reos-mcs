<?php

declare(strict_types=1);

namespace App\Services\Connections;

use App\Connections\ConnectionStatus;
use App\Models\Connection;
use App\Notifications\ConnectionNeedsReconnect;
use App\Services\Audit\AuditLogger;

/**
 * Health per connection, the way 2.4b does it per tool: three failed syncs in
 * a row mark it "failing" (still retried); a refused token means the person
 * must reconnect, and they're told once.
 */
final readonly class ConnectionHealth
{
    public const FAILING_AFTER = 3;

    public function __construct(private AuditLogger $audit) {}

    public function synced(Connection $connection): void
    {
        $connection->forceFill([
            'status' => ConnectionStatus::Active,
            'last_synced_at' => now(),
            'consecutive_failures' => 0,
            'last_error' => null,
        ])->save();
    }

    public function failed(Connection $connection, string $reason): void
    {
        $failures = $connection->consecutive_failures + 1;

        $connection->forceFill([
            'consecutive_failures' => $failures,
            'last_failed_at' => now(),
            'last_error' => $reason,
            'status' => $failures >= self::FAILING_AFTER && $connection->status === ConnectionStatus::Active ? ConnectionStatus::Failing : $connection->status,
        ])->save();
    }

    public function reconnectNeeded(Connection $connection, string $reason): void
    {
        if ($connection->status === ConnectionStatus::ReconnectNeeded) {
            return;
        }

        $connection->forceFill([
            'status' => ConnectionStatus::ReconnectNeeded,
            'last_failed_at' => now(),
            'last_error' => $reason,
        ])->save();

        $this->audit->record('connection.reconnect_needed', 'connection', (string) $connection->id, [
            'integration' => $connection->integration_id,
            'reason' => $reason,
        ]);

        $connection->contact()?->notify(new ConnectionNeedsReconnect($connection));
    }
}
