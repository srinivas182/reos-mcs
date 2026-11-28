<?php

declare(strict_types=1);

namespace App\Content\Learning;

use App\Agents\AgentModes;
use App\Authorization\Role;
use App\Content\CourseType;
use App\Content\Player\SeatTime;
use App\Licences\Ce\CeWallet;
use App\Models\Course;
use App\Models\User;
use App\RolePlay\RolePlays;
use App\RolePlay\RolePlayScorer;
use App\Services\Authorization\ActiveRole;
use Carbon\CarbonImmutable;

/**
 * Courses picked for an agent (A14.2, ADR 0062), in this order:
 *
 * 1. **CE first** when the license renews within 120 days and CE hours are
 *    still owed.
 * 2. **The skills role-play found weakest** (A14.1a): courses teaching the
 *    lessons behind the three fixes of the agent's recent practice and the
 *    assessment's weakest skills — or the one skill asked for (`topic`,
 *    from a fix's lesson link).
 * 3. **The agent's mode** (launch, grow, lead, own).
 *
 * Each pick says why and whether it can be listened to (audio-only). Only
 * live courses the agent can see in their state; finished courses are left
 * out.
 *
 * @phpstan-type Pick array{id: int, title: string, type: string, hours: float|null, reason: 'renewal'|'skill'|'mode', skill: string|null, audio: bool, first_lesson: int|null, percent: int}
 */
final readonly class CoursePicks
{
    public const LIMIT = 6;

    /** CE comes first when renewal is this close. */
    public const RENEWAL_DAYS = 120;

    /** Which skill each role-play part trains (A14.1a). */
    private const SKILL_OF_TYPE = [
        'listing' => 'listing_presentation',
        'objection' => 'objection_handling',
        'prospecting' => 'discovery_questions',
        'appraisal_gap' => 'objection_handling',
        'follow_up' => 'asking_for_business',
    ];

    public function __construct(
        private Learning $learning,
        private CeWallet $wallet,
        private AgentModes $modes,
        private RolePlays $rolePlays,
        private SeatTime $seatTime,
        private ActiveRole $roles,
    ) {}

    /** @return list<Pick> */
    public function for(User $user, ?string $topic = null, ?CarbonImmutable $today = null): array
    {
        if ($this->roles->for($user)?->role !== Role::Agent) {
            return [];
        }

        $available = array_values(array_filter($this->learning->courses($user), static fn (array $c): bool => ! $c['progress']['completed']));
        $courses = Course::query()->with('approvals')->whereIn('id', array_column($available, 'id'))->get()->keyBy('id');
        // Candidates in order of priority: [course, why, skill].
        $wanted = [];

        // 1. CE when renewal is near and hours are owed.
        $owed = false;

        foreach ($this->wallet->for($user, $today) as $wallet) {
            $days = $wallet['ce']['days_left'];
            $owed = $owed || ($days !== null && $days <= self::RENEWAL_DAYS && $wallet['ce']['hours_left'] > 0);
        }

        if ($owed) {
            foreach ($available as $course) {
                if ($course['type'] === CourseType::Ce->value) {
                    $wanted[] = [$course, 'renewal', null];
                }
            }
        }

        // 2. The skills to work on, the one asked for first.
        foreach ($this->skills($user, $topic) as $skill) {
            foreach ($available as $course) {
                $model = $courses->get($course['id']);

                if ($model instanceof Course && in_array($skill, $model->skills ?? [], true)) {
                    $wanted[] = [$course, 'skill', $skill];
                }
            }
        }

        // 3. The agent's mode.
        $mode = $this->modes->goalOf($user);

        foreach ($available as $course) {
            $model = $courses->get($course['id']);

            if ($model instanceof Course && in_array($mode, $model->modes ?? [], true)) {
                $wanted[] = [$course, 'mode', null];
            }
        }

        $picks = [];
        $seen = [];

        foreach ($wanted as [$course, $reason, $skill]) {
            if (count($picks) < self::LIMIT && ! in_array($course['id'], $seen, true)) {
                $seen[] = $course['id'];
                $model = $courses->get($course['id']);
                $picks[] = [
                    'id' => $course['id'],
                    'title' => $course['title'],
                    'type' => $course['type'],
                    'hours' => $course['hours'],
                    'reason' => $reason,
                    'skill' => $skill,
                    'audio' => $model instanceof Course && $this->seatTime->audioAllowed($model),
                    'first_lesson' => $course['lessons'][0]['id'] ?? null,
                    'percent' => $course['progress']['percent'],
                ];
            }
        }

        return $picks;
    }

    /**
     * The skills to work on: the one asked for, then the fixes from the
     * latest scored practice, then the assessment's weakest skills.
     *
     * @return list<string>
     */
    private function skills(User $user, ?string $topic): array
    {
        $skills = [];

        if ($topic !== null && in_array($topic, RolePlayScorer::LESSONS, true)) {
            $skills[] = $topic;
        }

        foreach ($this->rolePlays->latestFixes($user) as $lesson) {
            $skills[] = $lesson;
        }

        foreach ($this->rolePlays->weakest($user) as $type) {
            $skills[] = self::SKILL_OF_TYPE[$type];
        }

        return array_values(array_unique($skills));
    }
}
