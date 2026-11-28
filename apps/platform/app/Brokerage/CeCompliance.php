<?php

declare(strict_types=1);

namespace App\Brokerage;

use App\Authorization\Role;
use App\Content\CourseStatus;
use App\Content\CourseType;
use App\Models\CeCompletion;
use App\Models\Course;
use App\Models\Licence;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\CeBrokerReminder;
use App\Notifications\CourseAssigned;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Services\StateRules\CeRequirements;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CE compliance for the broker (A16.2, ADR 0077): every agent's renewal,
 * hours left and SAE status from TREC's rules (A5.2) and nightly file
 * (B10.3a), with **Remind all due** in one action and course assignment.
 *
 * - **Status:** done, on track, due (inside 90 days), behind (the same rule
 *   as oversight, A16.1) or overdue (the renewal date has passed).
 * - **Reminders** go to agents due, behind or overdue, at most once a week
 *   per agent, and say who sent them. Each is recorded and audited.
 * - **Assignments:** a live CE, qualifying or elective course for an
 *   agent, a team or everyone due, with an optional due date; done when the
 *   agent completes it.
 * - **Who:** the designated broker, and office admins and team leaders with
 *   "sponsor agents"; a team leader sees their team.
 *
 * @phpstan-type Row array{id: int, name: string, team: string|null, licence: string|null, status: 'done'|'on_track'|'due'|'behind'|'overdue'|'no_licence', due_on: string|null, days_left: int|null, hours_required: float, hours_done: float, hours_left: float, sae: bool, reminded_at: string|null, assignments: list<array{course: string, due_on: string|null, done: bool}>}
 * @phpstan-type Table array{brokerage: string, scope: 'brokerage'|'team', team: string|null, counts: array{agents: int, due: int, done: int}, rows: list<Row>, courses: list<array{id: int, title: string, type: string, hours: string|null}>}
 */
final readonly class CeCompliance
{
    /** A renewal inside this many days is "due". */
    public const DUE_DAYS = 90;

    /** At most one broker reminder per agent in this many days. */
    public const REMIND_EVERY_DAYS = 7;

    private const DUE = ['due', 'behind', 'overdue'];

    public function __construct(
        private BrokerageAccess $access,
        private BrokerageStaff $staff,
        private Oversight $oversight,
        private Memberships $memberships,
        private CeRequirements $requirements,
        private Tenancy $tenancy,
        private AuditLogger $audit,
    ) {}

    /** @return Table */
    public function table(User $user, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        [$brokerage, $team] = $this->place($user);
        $rows = [];

        foreach ($this->oversight->agents($brokerage, $team) as $agent) {
            $rows[] = $this->row($agent, $brokerage, $today);
        }

        $order = ['overdue' => 0, 'behind' => 1, 'due' => 2, 'no_licence' => 3, 'on_track' => 4, 'done' => 5];
        usort($rows, static fn (array $a, array $b): int => [$order[$a['status']], $a['days_left'] ?? 9999] <=> [$order[$b['status']], $b['days_left'] ?? 9999]);

        return [
            'brokerage' => $brokerage->name, 'scope' => $team === null ? 'brokerage' : 'team', 'team' => $team?->name,
            'counts' => ['agents' => count($rows), 'due' => count(array_filter($rows, static fn (array $r): bool => in_array($r['status'], self::DUE, true))), 'done' => count(array_filter($rows, static fn (array $r): bool => $r['status'] === 'done'))],
            'rows' => $rows,
            'courses' => array_values(Course::query()->where('status', CourseStatus::Live)->whereIn('type', [CourseType::Ce, CourseType::Qualifying, CourseType::Elective])
                ->where(static fn ($q) => $q->whereNull('organisation_id')->orWhere('organisation_id', $brokerage->id))->orderBy('title')->limit(200)->get(['id', 'title', 'type', 'hours'])
                ->map(static fn (Course $c): array => ['id' => $c->id, 'title' => $c->title, 'type' => $c->type->value, 'hours' => $c->hours])->all()),
        ];
    }

    /**
     * Remind all due agents in one action (the done-when): agents due, behind
     * or overdue who haven't been reminded this week. Returns how many.
     *
     * @return array{sent: int, skipped: int, table: Table}
     */
    public function remindDue(User $user, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        [$brokerage, $team] = $this->place($user);
        $sent = 0;
        $skipped = 0;

        foreach ($this->oversight->agents($brokerage, $team) as $agent) {
            $row = $this->row($agent, $brokerage, $today);

            if (! in_array($row['status'], self::DUE, true)) {
                continue;
            }

            if ($row['reminded_at'] !== null && CarbonImmutable::parse($row['reminded_at'])->gt(now()->subDays(self::REMIND_EVERY_DAYS))) {
                $skipped++;

                continue;
            }

            $agent->notify(new CeBrokerReminder((string) $user->name, $brokerage->name, $row['hours_left'], $row['due_on'], $row['sae']));
            $this->tenancy->run($brokerage->id, static fn () => DB::table('ce_broker_reminders')->insert([
                'organisation_id' => $brokerage->id, 'user_id' => $agent->id, 'sent_by' => $user->id, 'hours_left' => $row['hours_left'], 'due_on' => $row['due_on'], 'sent_at' => now(),
            ]));
            $sent++;
        }

        $this->audit->record('ce.reminded', 'organisation', (string) $brokerage->id, ['sent' => $sent, 'skipped' => $skipped], $user, $brokerage->id);

        return ['sent' => $sent, 'skipped' => $skipped, 'table' => $this->table($user, $today)];
    }

    /**
     * Assigns a course to agents (by id), or to everyone due.
     *
     * @param  list<int>|'due'  $agents
     * @return array{assigned: int, table: Table}
     */
    public function assign(User $user, int $courseId, array|string $agents, ?string $dueOn, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        [$brokerage, $team] = $this->place($user);
        $course = Course::query()->where('status', CourseStatus::Live)->where(static fn ($q) => $q->whereNull('organisation_id')->orWhere('organisation_id', $brokerage->id))->find($courseId)
            ?? throw ValidationException::withMessages(['course' => __('brokerage.ce.assign.errors.course')]);
        $mine = $this->oversight->agents($brokerage, $team)->keyBy('id');
        $targets = $agents === 'due'
            ? $mine->filter(fn (User $a): bool => in_array($this->row($a, $brokerage, $today)['status'], self::DUE, true))
            : $mine->only($agents);

        if ($targets->isEmpty()) {
            throw ValidationException::withMessages(['agents' => __('brokerage.ce.assign.errors.agents')]);
        }

        $assigned = 0;

        foreach ($targets as $agent) {
            $created = $this->tenancy->run($brokerage->id, static fn (): bool => DB::table('course_assignments')->insertOrIgnore([
                'organisation_id' => $brokerage->id, 'course_id' => $course->id, 'user_id' => $agent->id, 'assigned_by' => $user->id, 'due_on' => $dueOn,
                'created_at' => now(), 'updated_at' => now(),
            ]) > 0);

            if ($created) {
                $agent->notify(new CourseAssigned($course->title, $course->slug, $brokerage->name, $dueOn));
                $assigned++;
            }
        }

        $this->audit->record('ce.course_assigned', 'course', (string) $course->id, ['agents' => $targets->keys()->values()->all(), 'due_on' => $dueOn], $user, $brokerage->id);

        return ['assigned' => $assigned, 'table' => $this->table($user, $today)];
    }

    /**
     * Assignments done once the agent completes the course (called by the
     * nightly CE confirmation and when a REOS course is completed).
     */
    public function markCompleted(User $agent): int
    {
        return (int) $this->tenancy->asPlatform(static function () use ($agent): int {
            $done = CeCompletion::query()->where('user_id', $agent->id)->whereNotNull('course_id')->pluck('course_id')->all();

            return $done === [] ? 0 : DB::table('course_assignments')->where('user_id', $agent->id)->whereNull('completed_at')->whereIn('course_id', $done)->update(['completed_at' => now(), 'updated_at' => now()]);
        });
    }

    /** @return array{0: Organisation, 1: Organisation|null} */
    private function place(User $user): array
    {
        [$brokerage, $team] = $this->access->scope($user);
        $this->staff->allow($user, $brokerage, 'sponsor_agents');

        return [$brokerage, $team];
    }

    /** @return Row */
    private function row(User $agent, Organisation $brokerage, CarbonImmutable $today): array
    {
        $state = ($brokerage->states ?? [])[0] ?? 'TX';
        $licence = Licence::query()->where('user_id', $agent->id)->where('state_code', $state)->first();
        $ce = $licence === null ? null : $this->requirements->for($licence, $today);
        $reminded = $this->tenancy->run($brokerage->id, static fn (): mixed => DB::table('ce_broker_reminders')->where('organisation_id', $brokerage->id)->where('user_id', $agent->id)->max('sent_at'));
        $assignments = $this->tenancy->run($brokerage->id, static fn () => DB::table('course_assignments')->join('courses', 'courses.id', '=', 'course_assignments.course_id')
            ->where('course_assignments.organisation_id', $brokerage->id)->where('course_assignments.user_id', $agent->id)
            ->orderBy('course_assignments.id')->get(['courses.title', 'course_assignments.due_on', 'course_assignments.completed_at']));
        $status = match (true) {
            $ce === null => 'no_licence',
            $ce['complete'] => 'done',
            $ce['days_left'] !== null && $ce['days_left'] < 0 => 'overdue',
            $ce['days_left'] !== null && Oversight::ceBehind($ce['hours_left'], $ce['days_left'], $ce['first_renewal']) => 'behind',
            $ce['days_left'] !== null && $ce['days_left'] <= self::DUE_DAYS => 'due',
            default => 'on_track',
        };

        return [
            'id' => $agent->id, 'name' => (string) $agent->name, 'team' => $this->teamName($agent, $brokerage), 'licence' => $licence?->number, 'status' => $status,
            'due_on' => $ce['due_on'] ?? null, 'days_left' => $ce['days_left'] ?? null,
            'hours_required' => (float) ($ce['hours_required'] ?? 0), 'hours_done' => (float) ($ce['hours_done'] ?? 0), 'hours_left' => (float) ($ce['hours_left'] ?? 0),
            'sae' => (bool) ($ce['first_renewal'] ?? false),
            'reminded_at' => is_string($reminded) ? CarbonImmutable::parse($reminded)->toIso8601String() : null,
            'assignments' => array_values($assignments->map(static fn (object $a): array => [
                'course' => (string) $a->title, 'due_on' => is_string($a->due_on) ? substr($a->due_on, 0, 10) : null, 'done' => $a->completed_at !== null,
            ])->all()),
        ];
    }

    private function teamName(User $agent, Organisation $brokerage): ?string
    {
        foreach ($this->memberships->of($agent) as $m) {
            if ($m->role === Role::Agent && $m->organisation->parent_id === $brokerage->id) {
                return $m->organisation->name;
            }
        }

        return null;
    }
}
