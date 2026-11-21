<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Connections\ConnectionStatus;
use App\Connections\ConnectorRegistry;
use App\Connections\SubscribesToChanges;
use App\Integrations\IntegrationMode;
use App\Models\Connection;
use App\Services\Connections\ChangeChannels;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Hourly: open missing push channels and renew those ending within a day (B4.1). */
#[Signature('reos:connections:channels')]
#[Description('Open and renew push channels for connections (B4.1)')]
final class ConnectionsChannelsCommand extends Command
{
    public function handle(ConnectorRegistry $registry, ChangeChannels $channels): int
    {
        $checked = 0;

        Connection::query()
            ->whereIn('status', [ConnectionStatus::Active, ConnectionStatus::Failing])
            ->whereNotNull('last_synced_at')
            ->orderBy('id')
            ->chunkById(200, function ($connections) use ($registry, $channels, &$checked): void {
                foreach ($connections as $connection) {
                    if (! $registry->supports($connection->integration_id)) {
                        continue;
                    }

                    $mode = $registry->mode($connection->integration_id);

                    if ($mode === IntegrationMode::Off || $mode !== $connection->mode) {
                        continue;
                    }

                    $driver = $registry->driver($connection->integration_id, $mode);

                    if ($driver instanceof SubscribesToChanges) {
                        $channels->keep($connection, $driver);
                        $checked++;
                    }
                }
            });

        $this->info("Checked push channels for {$checked} connections.");

        return self::SUCCESS;
    }
}
