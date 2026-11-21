<?php

declare(strict_types=1);

namespace App\Enums;

/** Where a state is in its launch (web journey screen 61). */
enum StateStatus: string
{
    /** People can register and study for this state. */
    case Live = 'live';
    /** Rules, courses and approvals are being loaded. */
    case InPreparation = 'in_preparation';
    /** Not being prepared yet; people can join the waitlist. */
    case Waitlist = 'waitlist';
    case NotPlanned = 'not_planned';

    public function isLive(): bool
    {
        return $this === self::Live;
    }
}
