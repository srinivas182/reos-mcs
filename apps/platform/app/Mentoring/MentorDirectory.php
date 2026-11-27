<?php

declare(strict_types=1);

namespace App\Mentoring;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\MentorAssignment;
use App\Models\MentorSetting;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\Memberships;

/**
 * Who mentors whom at a brokerage (A13.1a, ADR 0053): an agent's assigned
 * mentor (free to them), the brokerage's settings for a mentor, and who
 * may book them. Bookings and the mentor program both read it.
 */
final readonly class MentorDirectory
{
    public function __construct(private Memberships $memberships) {}

    /** The agent's assigned mentor at their brokerage, if any. */
    public function assignedTo(User $agent): ?MentorAssignment
    {
        $brokerage = $this->brokerageOf($agent);

        return $brokerage === null ? null : self::assignment($brokerage, $agent);
    }

    /** Whether this mentor is the agent's assigned one (sessions are then free). */
    public function isAssigned(User $agent, int $mentorId): bool
    {
        return $this->assignedTo($agent)?->mentor_id === $mentorId;
    }

    /** The brokerage's settings for a mentor, or null when they aren't a brokerage mentor. */
    public function settingsFor(User $mentor): ?MentorSetting
    {
        $brokerage = $this->brokerageOf($mentor, Role::Mentor);

        return $brokerage === null ? null : self::settings($brokerage, $mentor);
    }

    /** Whether an agent may book this mentor ("who can book"). Instructors and other brokerages' rules don't apply. */
    public function canBook(User $agent, User $mentor): bool
    {
        $settings = $this->settingsFor($mentor);

        return $settings === null || $settings->who === 'everyone' || $this->isAssigned($agent, $mentor->id);
    }

    public function brokerageOf(User $user, Role $role = Role::Agent): ?Organisation
    {
        foreach ($this->memberships->of($user) as $membership) {
            if ($membership->role === $role && $membership->organisation->type === OrganisationType::Brokerage) {
                return $membership->organisation;
            }
        }

        return null;
    }

    /** @return list<int> the brokerage's agents */
    public function agentsOf(Organisation $brokerage): array
    {
        return array_values(array_map(static fn (User $u): int => $u->id, $this->memberships->holdersIn(Role::Agent, $brokerage)->all()));
    }

    public static function settings(Organisation $brokerage, User $mentor): MentorSetting
    {
        return MentorSetting::query()->firstOrNew(['organisation_id' => $brokerage->id, 'user_id' => $mentor->id], ['topics' => [], 'price_30_cents' => 0, 'price_60_cents' => 0, 'points' => true, 'max_mentees' => 5, 'who' => 'everyone']);
    }

    public static function assignment(Organisation $brokerage, User $agent): ?MentorAssignment
    {
        return MentorAssignment::query()->where('organisation_id', $brokerage->id)->where('agent_id', $agent->id)->whereNull('ended_at')->with('mentor')->latest('id')->first();
    }
}
