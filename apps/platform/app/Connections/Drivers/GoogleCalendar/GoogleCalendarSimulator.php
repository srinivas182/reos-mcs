<?php

declare(strict_types=1);

namespace App\Connections\Drivers\GoogleCalendar;

use App\Connections\Calendar\CalendarSimulator;

/** Google Calendar on demo data (ADR 0009 "Simulator" mode); see `CalendarSimulator`. */
final readonly class GoogleCalendarSimulator extends CalendarSimulator
{
    public function integration(): string
    {
        return GoogleCalendarDriver::INTEGRATION;
    }

    protected function scopes(): array
    {
        return GoogleCalendarDriver::SCOPES;
    }
}
