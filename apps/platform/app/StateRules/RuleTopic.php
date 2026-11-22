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
}
