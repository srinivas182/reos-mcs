<?php

declare(strict_types=1);

namespace App\StateRules;

/** The tabs on State rules (web journey screen 62). */
enum RuleTopic: string
{
    case Licensing = 'licensing';
    case Exam = 'exam';
    case Ce = 'ce';
    case Advertising = 'advertising';
    case Brokerage = 'brokerage';
    case Forms = 'forms';
    /** How approved courses are delivered: seat time and attendance (A7.1). */
    case Courses = 'courses';
    /** Agent modes (A11.1a): when REOS suggests Grow, Lead, and unlocks Own. */
    case Agents = 'agents';
}
