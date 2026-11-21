<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Connections\Drivers\GoogleCalendar\GoogleCalendarSimulator;
use App\Connections\SyncTrigger;
use App\Integrations\IntegrationMode;
use App\Jobs\SyncConnection;
use App\Models\Connection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * For demos and testing on demo data:
 *
 * - `--revoke` / `--restore`: behave as if the person revoked REOS at the tool.
 * - `--change-event="New title"`: rename the next event on the main
 *   calendar as if the person did it in Google, then deliver the change
 *   (as Google's push would). `--event=` picks the event, `--delete` deletes it.
 */
#[Signature('reos:connections:simulate {connection : Connection id} {--revoke} {--restore} {--change-event= : New title} {--event= : The tool\'s event id} {--delete}')]
#[Description('Simulate a revoked connection or a change in the tool, on demo data (B3.1, B4.1)')]
final class ConnectionsSimulateCommand extends Command
{
    public function handle(): int
    {
        $connection = Connection::query()->find((int) $this->argument('connection'));

        if ($connection === null || $connection->mode !== IntegrationMode::Simulator) {
            $this->error('Only Simulator (demo data) connections can be simulated.');

            return self::FAILURE;
        }

        if ($this->option('change-event') !== null || $this->option('delete')) {
            return $this->change($connection);
        }

        $revoked = (bool) $this->option('revoke') && ! $this->option('restore');
        $connection->forceFill(['meta' => [...$connection->meta, 'simulator_revoked' => $revoked]])->save();
        $this->info($revoked ? 'The next sync will be refused.' : 'The connection works again after reconnecting.');

        return self::SUCCESS;
    }

    private function change(Connection $connection): int
    {
        if ($connection->integration_id !== 'google-calendar') {
            $this->error('Only Google Calendar connections have events to change.');

            return self::FAILURE;
        }

        $event = $this->option('event');
        $title = $this->option('change-event');
        $changed = GoogleCalendarSimulator::changeInTool(
            $connection,
            $this->option('delete') ? ['deleted' => true] : ['title' => is_string($title) ? $title : ''],
            is_string($event) ? $event : null,
        );

        if ($changed === null) {
            $this->error('No upcoming event to change.');

            return self::FAILURE;
        }

        SyncConnection::dispatch($connection->id, 'events', SyncTrigger::Webhook);
        $id = is_string($changed['external_id'] ?? null) ? $changed['external_id'] : '';
        $this->info("Changed {$id} in the simulated Google Calendar; REOS is syncing it now.");

        return self::SUCCESS;
    }
}
