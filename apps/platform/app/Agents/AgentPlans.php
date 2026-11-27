<?php

declare(strict_types=1);

namespace App\Agents;

use App\Models\AgentPlan;
use App\Models\AgentPlanTask;
use App\Models\Connection;
use App\Models\Contact;
use App\Models\RolePlay;
use App\Models\User;
use App\Practice\StudyPlans;
use App\RolePlay\RolePlays;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * The 90-day plan (A11.1b, ADR 0044; agent screen 10): thirteen weeks in
 * four phases from REOS's template for the agent's goal, weekly targets
 * (halved for part-time), and tasks to tick off. Launch carries the
 * First-Closing Promise. A new goal starts a new plan; going back resumes
 * the old one.
 *
 * @phpstan-type PlanTask array{code: string, week: int, done: bool, auto: bool, repeats: bool, link: string|null}
 * @phpstan-type Plan array{goal: string, starts_on: string, ends_on: string, week: int, weeks: int, phases: list<array{code: string, from: int, to: int, current: bool}>, targets: array<string, float|int>, tasks: list<PlanTask>, done: int, total: int, promised: bool}
 */
final readonly class AgentPlans
{
    public const WEEKS = 13;

    /**
     * Per goal: weekly targets, then phases (code, first week, last week) with their tasks.
     *
     * @var array<string, array{targets: array<string, int>, phases: list<array{0: string, 1: int, 2: int, 3: list<string>}>}>
     */
    public const TEMPLATES = [
        'launch' => [
            'targets' => ['conversations' => 20, 'appointments' => 2, 'clients' => 1, 'ce_hours' => 1],
            'phases' => [
                ['setup', 1, 2, ['connect_tools', 'import_contacts', 'announce_licence', 'first_ce_lesson']],
                ['conversations', 3, 6, ['call_sphere', 'ask_referrals', 'mentor_checkin']],
                ['appointments', 7, 10, ['call_sphere', 'book_consultation', 'listing_presentation']],
                ['contract', 11, 13, ['call_sphere', 'review_contract', 'plan_closing']],
            ],
        ],
        'grow' => [
            'targets' => ['conversations' => 40, 'appointments' => 4, 'clients' => 3, 'ce_hours' => 1],
            'phases' => [
                ['pipeline', 1, 4, ['pipeline_review', 'past_client_touch']],
                ['referrals', 5, 8, ['pipeline_review', 'ask_referrals', 'anniversary_touch']],
                ['repeat', 9, 13, ['pipeline_review', 'past_client_touch', 'ask_referrals']],
            ],
        ],
        'lead' => [
            'targets' => ['conversations' => 30, 'appointments' => 3, 'clients' => 4, 'ce_hours' => 1],
            'phases' => [
                ['capacity', 1, 4, ['map_your_hours', 'pipeline_review']],
                ['first_hire', 5, 9, ['write_role', 'interview_candidates', 'pipeline_review']],
                ['team', 10, 13, ['team_huddle', 'pipeline_review']],
            ],
        ],
        'own' => [
            'targets' => ['conversations' => 20, 'appointments' => 2, 'clients' => 2, 'ce_hours' => 1],
            'phases' => [
                ['model', 1, 4, ['choose_model', 'broker_course']],
                ['business_plan', 5, 9, ['business_plan', 'comp_plan']],
                ['launch', 10, 13, ['broker_licence', 'go_live_checklist']],
            ],
        ],
    ];

    /** Tasks that come back every week; the rest are done once per phase. */
    public const REPEATS = ['call_sphere', 'pipeline_review', 'mentor_checkin', 'team_huddle'];

    /** Where each task is done (the phone and web open that screen). */
    public const LINKS = [
        'connect_tools' => 'connections', 'import_contacts' => 'contacts', 'announce_licence' => 'announce', 'first_ce_lesson' => 'learn',
        'call_sphere' => 'contacts', 'ask_referrals' => 'contacts', 'mentor_checkin' => 'mentors', 'book_consultation' => 'calendar',
        'listing_presentation' => 'listings', 'review_contract' => 'deals', 'plan_closing' => 'deals', 'pipeline_review' => 'deals',
        'past_client_touch' => 'contacts', 'anniversary_touch' => 'contacts', 'team_huddle' => 'calendar', 'interview_candidates' => 'calendar',
    ];

    public function __construct(private AgentModes $modes, private RolePlays $rolePlays) {}

    /** @return Plan */
    public function current(User $user): array
    {
        $plan = $this->plan($user);
        $week = $this->week($plan);
        $template = self::TEMPLATES[$plan->goal] ?? self::TEMPLATES['launch'];
        $done = AgentPlanTask::query()->where('agent_plan_id', $plan->id)->get();
        $tasks = [];
        $total = 0;
        $doneCount = 0;

        foreach ($template['phases'] as [$phase, $from, $to, $codes]) {
            foreach ($codes as $code) {
                $repeats = in_array($code, self::REPEATS, true);
                $weeks = $repeats ? range($from, $to) : [$from];

                foreach ($weeks as $taskWeek) {
                    $total++;
                    $isDone = $done->contains(fn (AgentPlanTask $t): bool => $t->code === $code && $t->week === $taskWeek) || $this->auto($user, $code);
                    $doneCount += $isDone ? 1 : 0;
                    $current = $repeats ? $taskWeek === $week : $week >= $from && $week <= $to;

                    if ($current) {
                        $tasks[] = ['code' => $code, 'week' => $taskWeek, 'done' => $isDone, 'auto' => $this->autoCodes($code), 'repeats' => $repeats, 'link' => self::LINKS[$code] ?? null];
                    }
                }
            }
        }

        // The skills assessment (A14.1a) adds a weekly role-play of the weakest skill, ticked by doing it.
        $weakest = $this->rolePlays->weakest($user)[0] ?? null;

        if ($weakest !== null) {
            $from = $plan->starts_on->addWeeks($week - 1)->startOfDay();
            $practised = RolePlay::query()->where('user_id', $user->id)->where('type', $weakest)->where('status', 'scored')->whereNull('skill_assessment_id')
                ->whereBetween('finished_at', [$from, $from->addWeek()])->exists();
            $tasks[] = ['code' => 'practice_'.$weakest, 'week' => $week, 'done' => $practised, 'auto' => true, 'repeats' => true, 'link' => 'role_play'];
            $total++;
            $doneCount += $practised ? 1 : 0;
        }

        return [
            'goal' => $plan->goal,
            'starts_on' => $plan->starts_on->toDateString(),
            'ends_on' => $plan->ends_on->toDateString(),
            'week' => $week,
            'weeks' => self::WEEKS,
            'phases' => array_values(array_map(static fn (array $p): array => ['code' => $p[0], 'from' => $p[1], 'to' => $p[2], 'current' => $week >= $p[1] && $week <= $p[2]], $template['phases'])),
            'targets' => $this->targets($user, $plan->goal),
            'tasks' => $tasks,
            'done' => $doneCount,
            'total' => $total,
            'promised' => $plan->promised_at !== null,
        ];
    }

    /** @return Plan */
    public function tick(User $user, string $code, int $week, bool $done): array
    {
        $plan = $this->plan($user);
        $known = false;

        foreach ((self::TEMPLATES[$plan->goal] ?? [])['phases'] ?? [] as [, $from, $to, $codes]) {
            $known = $known || (in_array($code, $codes, true) && $week >= $from && $week <= $to);
        }

        if (! $known) {
            throw ValidationException::withMessages(['code' => __('agents.plan.errors.task')]);
        }

        if ($done) {
            AgentPlanTask::query()->firstOrCreate(['agent_plan_id' => $plan->id, 'week' => $week, 'code' => $code], ['done_at' => now()]);
        } else {
            AgentPlanTask::query()->where('agent_plan_id', $plan->id)->where('week', $week)->where('code', $code)->delete();
        }

        return $this->current($user);
    }

    /**
     * The First-Closing Promise: committed.
     *
     * @return Plan
     */
    public function promise(User $user): array
    {
        $this->plan($user)->forceFill(['promised_at' => now()])->save();

        return $this->current($user);
    }

    /**
     * This goal's weekly targets; part-time halves them.
     *
     * @return array<string, float|int>
     */
    public function targets(User $user, string $goal): array
    {
        $targets = (self::TEMPLATES[$goal] ?? self::TEMPLATES['launch'])['targets'];
        $partTime = in_array('part_time', $this->modes->flagsOf($user), true);

        return array_map(static fn (int $n): float|int => $partTime ? ($n % 2 === 0 ? intdiv($n, 2) : $n / 2) : $n, $targets);
    }

    /** The plan for the agent's goal, started today the first time. */
    public function plan(User $user): AgentPlan
    {
        $goal = $this->modes->goalOf($user);
        $today = CarbonImmutable::now(StudyPlans::TIMEZONE)->startOfDay();

        return AgentPlan::query()->firstOrCreate(
            ['user_id' => $user->id, 'goal' => $goal],
            ['starts_on' => $today->toDateString(), 'ends_on' => $today->addWeeks(self::WEEKS)->toDateString()],
        );
    }

    /** Week 1 to 13 of the plan. */
    public function week(AgentPlan $plan): int
    {
        $today = CarbonImmutable::now(StudyPlans::TIMEZONE)->startOfDay();
        $days = (int) $plan->starts_on->diffInDays($today, false);

        return max(1, min(self::WEEKS, intdiv(max(0, $days), 7) + 1));
    }

    /** Tasks REOS ticks itself: a connected tool, 20 contacts. */
    private function auto(User $user, string $code): bool
    {
        return match ($code) {
            'connect_tools' => Connection::query()->where('owner_type', Connection::OWNER_USER)->where('owner_id', $user->id)->exists(),
            'import_contacts' => Contact::query()->where('owner_type', 'user')->where('owner_id', $user->id)->count() >= 20,
            default => false,
        };
    }

    private function autoCodes(string $code): bool
    {
        return in_array($code, ['connect_tools', 'import_contacts'], true);
    }
}
