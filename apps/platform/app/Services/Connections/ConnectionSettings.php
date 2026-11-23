<?php

declare(strict_types=1);

namespace App\Services\Connections;

use App\Connections\ConnectorRegistry;
use App\Connections\Drivers\Dotloop\DotloopDriver;
use App\Connections\Drivers\Dotloop\DotloopProfiles;
use App\Http\Requests\Connections\UpdateConnectionRequest;
use App\Models\Connection;
use App\Models\User;
use App\Services\Calendar\CalendarConnections;
use Illuminate\Validation\ValidationException;

/** "What to sync" from the website and the phone alike (screens 7 and 16). */
final readonly class ConnectionSettings
{
    public function __construct(
        private Connections $connections,
        private CalendarConnections $calendars,
    ) {}

    /** @throws ValidationException */
    public function save(User $user, Connection $connection, UpdateConnectionRequest $request): Connection
    {
        abort_unless($this->connections->canManage($user, $connection), 404);

        if ($request->choosesCalendars()) {
            if (! ConnectorRegistry::isCalendar($connection->integration_id)) {
                throw ValidationException::withMessages(['calendars' => __('calendar.errors.notACalendar')]);
            }

            $this->calendars->choose($connection, $request->calendarModes(), $request->writeCalendar());
        }

        $profiles = $request->profiles();

        if ($profiles !== null) {
            if ($connection->integration_id !== DotloopDriver::INTEGRATION) {
                throw ValidationException::withMessages(['profiles' => __('deals.errors.noProfiles')]);
            }
            if (array_diff($profiles, array_column(DotloopProfiles::known($connection), 'id')) !== []) {
                throw ValidationException::withMessages(['profiles' => __('deals.errors.unknownProfile')]);
            }

            $this->connections->chooseProfiles($connection, $profiles);
        }

        $history = $request->history();

        if ($history !== null) {
            $this->connections->changeHistory($connection, $history);
        }

        return $connection;
    }
}
