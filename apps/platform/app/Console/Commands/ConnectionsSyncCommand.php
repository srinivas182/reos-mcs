<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Connections\ConnectionStatus;
use App\Connections\ConnectorRegistry;
use App\Connections\SyncTrigger;
use App\Jobs\SyncConnection;
use App\Models\Connection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Every 15 minutes: queue a sync for each connection not synced in that time. */
#[Signature('reos:connections:sync {--minutes=15 : Sync connections older than this}')]
#[Description('Queue syncs for connections that are due (B3.1)')]
final class ConnectionsSyncCommand extends Command
{
    public function handle(ConnectorRegistry $registry): int
    {
        $due = now()->subMinutes(max(1, (int) $this->option('minutes')));
        $queued = 0;

        Connection::query()
            ->whereIn('status', [ConnectionStatus::Active, ConnectionStatus::Failing])
            ->where(static fn ($query) => $query->whereNull('last_synced_at')->orWhere('last_synced_at', '<', $due))
            ->orderBy('id')
            ->chunkById(500, function ($connections) use ($registry, &$queued): void {
                foreach ($connections as $connection) {
                    // Off, paused or a different run mode: the engine decides what to do.
                    if (! $registry->supports($connection->integration_id)) {
                        continue;
                    }

                    $mode = $registry->mode($connection->integration_id);
                    if ($mode !== $connection->mode) {
                        continue;
                    }

                    foreach (array_keys($registry->driver($connection->integration_id, $mode)->streams()) as $stream) {
                        SyncConnection::dispatch($connection->id, $stream, SyncTrigger::Scheduled);
                        $queued++;
                    }
                }
            });

        $this->info("Queued {$queued} syncs.");

        return self::SUCCESS;
    }
}
