<?php

declare(strict_types=1);

namespace App\Services\Connections;

use App\Connections\ConnectorRegistry;
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

        $history = $request->history();

        if ($history !== null) {
            $this->connections->changeHistory($connection, $history);
        }

        return $connection;
    }
}
