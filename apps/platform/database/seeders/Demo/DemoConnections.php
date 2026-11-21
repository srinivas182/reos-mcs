<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Connections\ConnectionStatus;
use App\Connections\Drivers\GoogleCalendar\GoogleCalendarDriver;
use App\Connections\Drivers\GoogleCalendar\GoogleCalendarSimulator;
use App\Connections\SimulatorSignature;
use App\Connections\SyncTrigger;
use App\Integrations\IntegrationMode;
use App\Models\CalendarEvent;
use App\Models\Connection;
use App\Models\User;
use App\Services\Calendar\CalendarEvents;
use App\Services\Calendar\CalendarWriter;
use App\Services\Connections\SyncEngine;
use Carbon\CarbonImmutable;

/**
 * Demo Google Calendar connections on the Simulator (B3.1, B4.1), so the
 * journey's connection and calendar screens have something to show:
 *
 * - Linda Park: connected and syncing both ways; her personal calendar is
 *   shared as busy only. REOS added a showing and a deadline to her Google
 *   Calendar, and the showing changed in both places (the conflict trail).
 * - James Miller: stopped syncing two days ago and needs reconnecting
 *   (Connections and Sync journey, screens 26–27); an appointment he made
 *   since is waiting to be written.
 */
final readonly class DemoConnections
{
    public function __construct(
        private SyncEngine $engine,
        private CalendarEvents $events,
        private CalendarWriter $writer,
    ) {}

    public function seed(): void
    {
        $linda = $this->connect('linda.park', broken: false);
        $james = $this->connect('james.miller', broken: true);

        if ($linda !== null) {
            $this->lindasWeek($linda);
        }

        if ($james !== null) {
            $owner = User::query()->findOrFail($james->owner_id);
            $this->make($owner, [
                'title' => 'Buyer consultation — Ana Torres',
                'kind' => 'appointment',
                'starts_at' => $this->houston(2, 10),
                'ends_at' => $this->houston(2, 11),
                'location' => 'Lone Star Realty, Katy office',
            ]);
        }
    }

    private function lindasWeek(Connection $connection): void
    {
        $linda = User::query()->findOrFail($connection->owner_id);
        $showing = $this->make($linda, [
            'title' => 'Showing — 1234 Oak Ridge Dr',
            'kind' => 'showing',
            'starts_at' => $this->houston(1, 14),
            'ends_at' => $this->houston(1, 14, 45),
            'location' => '1234 Oak Ridge Dr, Cypress, TX',
            'attendees' => ['garcia.family@example.com'],
        ]);
        $this->make($linda, [
            'title' => 'Option period ends — 1234 Oak Ridge Dr',
            'kind' => 'deadline',
            'all_day' => true,
            // All-day: midnight UTC on Houston's date, ending the next midnight.
            'starts_at' => CarbonImmutable::parse(CarbonImmutable::now('America/Chicago')->addDays(4)->toDateString(), 'UTC'),
            'ends_at' => CarbonImmutable::parse(CarbonImmutable::now('America/Chicago')->addDays(5)->toDateString(), 'UTC'),
        ]);

        // Changed in REOS while Google couldn't be reached, and in Google too.
        $connection->forceFill(['status' => ConnectionStatus::ReconnectNeeded])->save();
        $this->events->update($showing, ['starts_at' => $this->houston(1, 15), 'ends_at' => $this->houston(1, 15, 45)]);
        GoogleCalendarSimulator::changeInTool($connection, ['title' => 'Showing — 1234 Oak Ridge Dr (Garcias running late)'], $showing->external_id);
        $connection->forceFill(['status' => ConnectionStatus::Active])->save();
        $this->engine->run($connection->refresh(), 'events', SyncTrigger::Webhook);
        // REOS's change was newer: it goes back over Google's, which is kept for Undo.
        $this->writer->push($showing->refresh());
    }

    /**
     * Made in REOS and written to the calendar now (the seeder doesn't wait for the queue).
     *
     * @param  array<string, mixed>  $values
     */
    private function make(User $user, array $values): CalendarEvent
    {
        $event = $this->events->create($user, $values);
        $this->writer->push($event->refresh());

        return $event->refresh();
    }

    /** A time in Houston, some days from today. */
    private function houston(int $days, int $hour, int $minute = 0): CarbonImmutable
    {
        return CarbonImmutable::now('America/Chicago')->addDays($days)->setTime($hour, $minute)->utc();
    }

    private function connect(string $persona, bool $broken): ?Connection
    {
        $user = User::query()->where('email', 'like', "{$persona}@%")->first();

        if ($user === null || $user->email === null) {
            return null;
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
                // REOS's events go to the main calendar; Linda shares her personal one as busy only.
                'settings' => ['history' => '3m', 'write_calendar' => 'primary', ...($broken ? [] : ['calendars' => ['primary' => 'full', 'personal' => 'busy']])],
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
                'meta' => [...$connection->meta, 'simulator_revoked' => true],
            ])->save();
        }

        return $connection;
    }
}
