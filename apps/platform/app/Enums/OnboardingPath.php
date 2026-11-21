<?php

declare(strict_types=1);

namespace App\Enums;

/** The cards on "Choose your path" (mobile journey screen 1). */
enum OnboardingPath: string
{
    case BecomeAgent = 'become_agent';
    case LicensedAgent = 'licensed_agent';
    /** Broker, team and association set-up finishes on the website. */
    case Broker = 'broker';
    case Instructor = 'instructor';
    case Provider = 'provider';
}
