<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Connections\ConnectionStatus;
use App\Connections\Drivers\GoogleCalendar\GoogleCalendarDriver;
use App\Connections\SimulatorSignature;
use App\Connections\SyncTrigger;
use App\Integrations\IntegrationMode;
use App\Models\Connection;
use App\Models\User;
use App\Services\Connections\SyncEngine;

/**
 * Demo Google Calendar connections on the Simulator (B3.1), so the journey's
 * connection screens have something to show:
 *
 * - Linda Park: connected and syncing.
 * - James Miller: stopped syncing two days ago and needs reconnecting
 *   (Connections and Sync journey, screens 26–27).
 */
final readonly class DemoConnections
{
    public function __construct(private SyncEngine $engine) {}

    public function seed(): void
    {
        $this->connect('linda.park', broken: false);
        $this->connect('james.miller', broken: true);
    }

    private function connect(string $persona, bool $broken): void
    {
        $user = User::query()->where('email', 'like', "{$persona}@%")->first();

        if ($user === null || $user->email === null) {
            return;
        }

        $connection = Connection::query()->updateOrCreate(
            [
                'integration_id' => GoogleCalendarDriver::INTEGRATION,
                'owner_type' => Connection::OWNER_USER,
                'owner_id' => $user->id,
                'external_account_id' => 'sim-'.substr(hash('sha256', $user->email), 0, 16),
            ],
            [
                'connected_by' => $user->id,
                'mode' => IntegrationMode::Simulator,
                'account_label' => $user->email,
                'scopes' => GoogleCalendarDriver::SCOPES,
                'status' => ConnectionStatus::Active,
                'access_token' => SimulatorSignature::seal(['account' => $user->email, 'kind' => 'access', 'nonce' => 'demo']),
                'refresh_token' => SimulatorSignature::seal(['account' => $user->email, 'kind' => 'refresh']),
                'token_expires_at' => now()->addHour(),
                'settings' => ['history' => '3m'],
                'cursors' => [],
                'meta' => [],
            ],
        );

        $this->engine->run($connection, 'events', SyncTrigger::First);

        if ($broken) {
            $connection->forceFill([
                'status' => ConnectionStatus::ReconnectNeeded,
                'last_error' => 'connections.errors.refused',
                'last_failed_at' => now()->subDays(2),
                'last_synced_at' => now()->subDays(2),
                'meta' => ['simulator_revoked' => true],
            ])->save();
        }
    }
}
