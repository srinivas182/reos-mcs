<?php

declare(strict_types=1);

namespace App\Enums;

/** Exam topics from the licensing content outline, as quiz questions are tagged. */
enum QuizTopic: string
{
    case Agency = 'agency';
    case Contracts = 'contracts';
    case Math = 'math';
    case Ownership = 'ownership';
    case Finance = 'finance';
    case StateRules = 'state_rules';
}
