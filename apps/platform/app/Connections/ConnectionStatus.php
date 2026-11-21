<?php

declare(strict_types=1);

namespace App\Connections;

/** Where a connection stands, as the person sees it. */
enum ConnectionStatus: string
{
    /** Syncing normally. */
    case Active = 'active';
    /** The tool refused the sign-in (revoked, expired, or REOS switched run mode): the person must sign in again. */
    case ReconnectNeeded = 'reconnect_needed';
    /** Syncs keep failing for another reason; REOS keeps retrying. */
    case Failing = 'failing';

    public function syncs(): bool
    {
        return $this !== self::ReconnectNeeded;
    }
}
