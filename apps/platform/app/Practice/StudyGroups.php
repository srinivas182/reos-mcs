<?php

declare(strict_types=1);

namespace App\Practice;

use App\Models\Challenge;
use App\Models\Organisation;
use App\Models\StudyGroup;
use App\Models\TestAttempt;
use App\Models\User;
use App\Notifications\GroupChallenge;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Study groups (A9.3c, candidate screen 52): private groups of up to 20,
 * for candidates who opted in from Profile, joined by invite link.
 *
 * - A weekly leaderboard (Monday to Sunday, Texas time) of questions
 *   answered and accuracy, first names only.
 * - A member's challenge goes to the whole group.
 * - A school can make a group for a class; the school isn't a member and
 *   sees how many joined, not their scores.
 * - Turning study groups off leaves every group.
 *
 * @phpstan-type Row array{first_name: string, questions: int, accuracy: int|null, mine: bool}
 * @phpstan-type Group array{id: int, uuid: string, name: string, link: string, school: string|null, members: int, max: int, mine: bool, leaderboard: list<Row>, week_start: string}
 */
final readonly class StudyGroups
{
    public const MAX_MEMBERS = 20;

    public function __construct(private Challenges $challenges, private AuditLogger $audit) {}

    /** Turns study groups on or off from Profile; off leaves every group. */
    public function optIn(User $user, bool $on): void
    {
        DB::transaction(function () use ($user, $on): void {
            $user->forceFill(['study_groups_opt_in' => $on])->save();

            if (! $on) {
                DB::table('study_group_members')->where('user_id', $user->id)->delete();
            }
        });
    }

    /** A candidate's own group: they're its first member. */
    public function create(User $user, string $name): StudyGroup
    {
        $this->mustOptIn($user);

        return DB::transaction(function () use ($user, $name): StudyGroup {
            $group = StudyGroup::query()->create(['uuid' => (string) Str::uuid(), 'name' => trim($name), 'owner_id' => $user->id]);
            $group->members()->attach($user->id, ['joined_at' => now()]);

            return $group;
        });
    }

    /** A school's group for a class: candidates join by its invite link. */
    public function createForSchool(Organisation $school, User $admin, string $name): StudyGroup
    {
        $group = StudyGroup::query()->create(['uuid' => (string) Str::uuid(), 'name' => trim($name), 'owner_id' => $admin->id, 'organisation_id' => $school->id]);
        $this->audit->record('study_group.created', 'study_group', (string) $group->id, ['name' => $group->name], $admin, $school->id);

        return $group;
    }

    /**
     * Join by invite link.
     *
     * @throws ValidationException not opted in, or the group is full
     */
    public function join(User $user, StudyGroup $group): void
    {
        $this->mustOptIn($user);

        DB::transaction(function () use ($user, $group): void {
            $locked = StudyGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();

            if ($locked->members()->where('users.id', $user->id)->exists()) {
                return;
            }

            if ($locked->members()->count() >= self::MAX_MEMBERS) {
                throw ValidationException::withMessages(['group' => __('practice.groups.errors.full', ['max' => self::MAX_MEMBERS])]);
            }

            $locked->members()->attach($user->id, ['joined_at' => now()]);
        });
    }

    public function leave(User $user, StudyGroup $group): void
    {
        $group->members()->detach($user->id);
    }

    public function isMember(User $user, StudyGroup $group): bool
    {
        return $group->members()->where('users.id', $user->id)->exists();
    }

    /** A member challenges the whole group: everyone else hears about it. */
    public function challenge(User $user, StudyGroup $group): Challenge
    {
        if (! $this->isMember($user, $group)) {
            throw ValidationException::withMessages(['group' => __('practice.groups.errors.notMember')]);
        }

        $challenge = $this->challenges->create($user, $group->id);
        $from = Challenges::firstName($user);

        foreach ($group->members()->where('users.id', '!=', $user->id)->get() as $member) {
            $member->notify(new GroupChallenge($challenge->uuid, $from, $group->name));
        }

        return $challenge;
    }

    /**
     * The candidate's groups, each with this week's leaderboard.
     *
     * @return list<Group>
     */
    public function mine(User $user): array
    {
        return array_values(StudyGroup::query()->whereHas('members', static fn ($q) => $q->where('users.id', $user->id))
            ->orderBy('name')->get()->map(fn (StudyGroup $g): array => $this->show($g, $user))->all());
    }

    /** @return Group */
    public function show(StudyGroup $group, User $viewer, bool $board = true): array
    {
        $members = $group->members()->get();
        $monday = WeeklyReports::weekOf();
        $school = $group->organisation_id === null ? null : Organisation::query()->whereKey($group->organisation_id)->value('name');

        return [
            'id' => $group->id,
            'uuid' => $group->uuid,
            'name' => $group->name,
            'link' => route('groups.show', $group->uuid),
            'school' => is_string($school) ? $school : null,
            'members' => $members->count(),
            'max' => self::MAX_MEMBERS,
            'mine' => $members->contains('id', $viewer->id),
            'leaderboard' => $board ? $this->leaderboard(array_values($members->all()), $viewer, $monday) : [],
            'week_start' => $monday->toDateString(),
        ];
    }

    /**
     * Questions answered this week and accuracy, most questions first.
     *
     * @param  list<User>  $members
     * @return list<Row>
     */
    public function leaderboard(array $members, User $viewer, CarbonImmutable $monday): array
    {
        $ids = array_map(static fn (User $u): int => $u->id, $members);
        $start = $monday->startOfDay()->utc();
        $end = $monday->addDays(7)->startOfDay()->utc();
        $totals = [];

        foreach (TestAttempt::query()->whereIn('user_id', $ids)->whereNotNull('submitted_at')->where('submitted_at', '>=', $start)->where('submitted_at', '<', $end)->get(['user_id', 'by_topic']) as $attempt) {
            foreach ($attempt->by_topic ?? [] as $topic) {
                $totals[$attempt->user_id]['questions'] = ($totals[$attempt->user_id]['questions'] ?? 0) + (int) $topic['total'];
                $totals[$attempt->user_id]['correct'] = ($totals[$attempt->user_id]['correct'] ?? 0) + (int) $topic['correct'];
            }
        }

        $rows = array_map(static function (User $u) use ($totals, $viewer): array {
            $questions = $totals[$u->id]['questions'] ?? 0;
            $correct = $totals[$u->id]['correct'] ?? 0;

            return ['first_name' => Challenges::firstName($u), 'questions' => $questions, 'accuracy' => $questions === 0 ? null : (int) round(100 * $correct / $questions), 'mine' => $u->id === $viewer->id];
        }, $members);
        usort($rows, static fn (array $a, array $b): int => [$b['questions'], $b['accuracy'] ?? -1, $a['first_name']] <=> [$a['questions'], $a['accuracy'] ?? -1, $b['first_name']]);

        return $rows;
    }

    /** @throws ValidationException when study groups are off in Profile */
    private function mustOptIn(User $user): void
    {
        if (! $user->study_groups_opt_in) {
            throw ValidationException::withMessages(['group' => __('practice.groups.errors.optIn')]);
        }
    }
}
