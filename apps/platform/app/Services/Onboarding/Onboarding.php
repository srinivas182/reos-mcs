<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Authorization\Role;
use App\Enums\Locale;
use App\Enums\OnboardingPath;
use App\Models\Organisation;
use App\Models\State;
use App\Models\User;
use App\Services\Authorization\Memberships;
use Illuminate\Support\Carbon;

/**
 * What a person chose while getting started: their path, target state and
 * the 60-second setup (mobile journey screens 1, 2 and 8). Choosing "I want
 * to become an agent" makes them a candidate on their own; a code from a
 * school or brokerage (A4.3) moves them there.
 */
final readonly class Onboarding
{
    public function __construct(private Memberships $memberships) {}

    /**
     * @param  array{path?: OnboardingPath|null, state?: State|null, exam_month?: Carbon|null, minutes_per_day?: int|null, locale?: Locale|null, reminder_time?: string|null, complete?: bool}  $choices
     */
    public function save(User $user, array $choices): User
    {
        $changes = [];

        if (array_key_exists('path', $choices) && $choices['path'] !== null) {
            $changes['onboarding_path'] = $choices['path']->value;
        }
        if (array_key_exists('state', $choices) && $choices['state'] !== null) {
            $changes['target_state'] = $choices['state']->code;
        }
        if (array_key_exists('exam_month', $choices)) {
            $changes['exam_month'] = $choices['exam_month']?->startOfMonth()->toDateString();
        }
        if (array_key_exists('minutes_per_day', $choices)) {
            $changes['minutes_per_day'] = $choices['minutes_per_day'];
        }
        if (array_key_exists('reminder_time', $choices)) {
            $changes['reminder_time'] = $choices['reminder_time'];
        }
        if (($choices['locale'] ?? null) instanceof Locale) {
            $changes['locale'] = $choices['locale'];
        }
        if (($choices['complete'] ?? false) && $user->onboarded_at === null) {
            $changes['onboarded_at'] = now();
        }

        $user->forceFill($changes)->save();

        if ($user->onboarding_path === OnboardingPath::BecomeAgent->value) {
            $this->becomeCandidate($user);
        }

        return $user;
    }

    /** Candidates who came without a school's code study on their own. */
    private function becomeCandidate(User $user): void
    {
        foreach ($this->memberships->of($user) as $membership) {
            if ($membership->role === Role::Candidate) {
                return;
            }
        }

        $this->memberships->grant($user, Role::Candidate, Organisation::platform(), $user);
    }
}
