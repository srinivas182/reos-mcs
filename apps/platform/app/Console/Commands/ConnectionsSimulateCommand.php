<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Integrations\IntegrationMode;
use App\Models\Connection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * For demos and testing on demo data: make a Simulator connection behave as
 * if the person revoked REOS at the tool (`--revoke`), or undo it.
 */
#[Signature('reos:connections:simulate {connection : Connection id} {--revoke} {--restore}')]
#[Description('Simulate a revoked connection on demo data (B3.1)')]
final class ConnectionsSimulateCommand extends Command
{
    public function handle(): int
    {
        $connection = Connection::query()->find((int) $this->argument('connection'));

        if ($connection === null || $connection->mode !== IntegrationMode::Simulator) {
            $this->error('Only Simulator (demo data) connections can be simulated.');

            return self::FAILURE;
        }

        $revoked = (bool) $this->option('revoke') && ! $this->option('restore');
        $connection->forceFill(['meta' => [...$connection->meta, 'simulator_revoked' => $revoked]])->save();
        $this->info($revoked ? 'The next sync will be refused.' : 'The connection works again after reconnecting.');

        return self::SUCCESS;
    }
}
