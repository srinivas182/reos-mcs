<?php

declare(strict_types=1);

namespace App\Connections;

/** Why a sync ran. */
enum SyncTrigger: string
{
    /** Straight after connecting, with the chosen history. */
    case First = 'first';
    /** The 15-minute schedule. */
    case Scheduled = 'scheduled';
    /** The tool told REOS something changed. */
    case Webhook = 'webhook';
    /** "Sync now". */
    case Manual = 'manual';
}
