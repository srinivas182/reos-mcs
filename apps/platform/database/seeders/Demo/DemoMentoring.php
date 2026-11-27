<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Mentoring\MentorProgram;
use App\Models\MentorAssignment;
use App\Models\Organisation;
use App\Models\User;

/**
 * Lone Star Realty's mentor program (A13.1a): Dana Brooks mentors first
 * deals and contracts, $40 / $75 a session or reward points, up to five
 * mentees, and is James Miller's assigned mentor (free to him), as in the
 * Broker and Team Leader Journey ("Adds Sarah Kim and Dana Brooks as
 * mentors").
 */
final readonly class DemoMentoring
{
    public function __construct(private MentorProgram $program) {}

    public function seed(): void
    {
        $lone = Organisation::query()->where('name', 'Lone Star Realty')->first();
        $linda = User::query()->where('email', 'linda.park@'.Personas::DOMAIN)->first();
        $dana = User::query()->where('email', 'dana.brooks@'.Personas::DOMAIN)->first();
        $james = User::query()->where('email', 'james.miller@'.Personas::DOMAIN)->first();

        if ($lone === null || $linda === null || $dana === null || $james === null || MentorAssignment::query()->where('organisation_id', $lone->id)->exists()) {
            return;
        }

        $this->program->save($lone, $dana, ['topics' => ['first_deals', 'contracts', 'listings'], 'price_30_cents' => 4000, 'price_60_cents' => 7500, 'points' => true, 'max_mentees' => 5, 'who' => 'everyone'], $linda);
        $this->program->assign($lone, $james, $dana, $linda);
    }
}
