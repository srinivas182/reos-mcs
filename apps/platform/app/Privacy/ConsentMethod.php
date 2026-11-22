<?php

declare(strict_types=1);

namespace App\Privacy;

/** How a consent was given or withdrawn (A6.3). */
enum ConsentMethod: string
{
    case Web = 'web';
    case App = 'app';
    // By a REOS admin on the person's behalf (for example, an opt-out asked for by email).
    case Admin = 'admin';
    // By replying STOP or HELP to a text (B6.2).
    case Sms = 'sms';
}
