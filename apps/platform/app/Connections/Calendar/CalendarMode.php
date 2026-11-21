<?php

declare(strict_types=1);

namespace App\Connections\Calendar;

/**
 * What REOS does with one of the person's calendars (Connections and Sync
 * journey, screen 16).
 */
enum CalendarMode: string
{
    /** Events are read, and changes made in REOS go back. */
    case Full = 'full';

    /** Only the times are read, shown as "Busy": no title, place or guests. */
    case Busy = 'busy';

    /** Not read at all. */
    case Off = 'off';
}
