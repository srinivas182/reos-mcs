<?php

declare(strict_types=1);

namespace App\Connections\Drivers\OutlookCalendar;

use App\Connections\Calendar\CalendarSimulator;

/** Outlook Calendar on demo data (ADR 0009 "Simulator" mode); see `CalendarSimulator`. */
final readonly class OutlookCalendarSimulator extends CalendarSimulator
{
    public function integration(): string
    {
        return OutlookCalendarDriver::INTEGRATION;
    }

    protected function scopes(): array
    {
        return OutlookCalendarDriver::SCOPES;
    }
}
