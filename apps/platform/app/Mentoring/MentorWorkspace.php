<?php

declare(strict_types=1);

namespace App\Mentoring;

use App\Agents\AgentModes;
use App\Agents\AgentPlans;
use App\Agents\AgentToday;
use App\Authorization\Role;
use App\Models\Deal;
use App\Models\MenteeNote;
use App\Models\MentorAssignment;
use App\Models\Organisation;
use App\Models\SessionBooking;
use App\Models\Todo;
use App\Models\User;
use App\Services\Todos\Todos;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * The mentor's workspace (A13.1b, ADR 0053; mentor screens 22–24).
 *
 * **Today** shows the mentor's mentees, this week's sessions, who needs
 * help (quiet for a week, no first session yet, behind on their plan) and
 * wrap-ups to send. **Mentees** are the agents assigned to the mentor by the
 * brokerage plus those who booked them. **Mentee detail** shows the mode
 * and week of the plan, plan progress, this week's scorecard, deal counts,
 * sessions together, goals set together (they're the mentee's to-dos) and
 * the mentor's **private notes**, which only the mentor sees. A mentee's
 * CRM contacts, clients and messages are never shown.
 *
 * @phpstan-type MenteeRow array{id: int, name: string, goal: string, assigned: bool, signal: 'quiet'|'first_session'|'behind'|'ok', last_active: string|null, next_session: string|null}
 * @phpstan-type SessionRow array{uuid: string, agent: string, agent_id: int, starts_at: string, minutes: int, topic: string, status: string, session: string|null, summary: string|null, tasks_sent: bool}
 */
final readonly class MentorWorkspace
{
    public const QUIET_DAYS = 7;

    public const BEHIND = 3;

    public function __construct(private MentorDirectory $directory, private AgentModes $modes, private AgentPlans $plans, private AgentToday $today, private Todos $todos) {}

    /**
     * Today for mentors (screen 22).
     *
     * @return array{brokerage: string, mentees: int, sessions_this_week: int, needs: list<MenteeRow>, upcoming: list<SessionRow>, wrap_ups: list<SessionRow>}
     */
    public function today(User $mentor, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $brokerage = $this->brokerage($mentor);
        $mentees = $this->mentees($mentor, 'all', $now);
        $week = [$now->startOfWeek(CarbonImmutable::MONDAY)->utc(), $now->startOfWeek(CarbonImmutable::MONDAY)->addWeek()->utc()];

        return [
            'brokerage' => $brokerage->name,
            'mentees' => count($mentees),
            'sessions_this_week' => SessionBooking::query()->where('instructor_id', $mentor->id)->whereIn('status', ['confirmed', 'completed'])->whereBetween('starts_at', $week)->count(),
            'needs' => array_slice(array_values(array_filter($mentees, static fn (array $m): bool => $m['signal'] !== 'ok')), 0, 5),
            'upcoming' => $this->sessions(SessionBooking::query()->where('instructor_id', $mentor->id)->where('status', 'confirmed')->where('ends_at', '>', $now->utc())->orderBy('starts_at')->limit(5)->get()),
            'wrap_ups' => $this->sessions(SessionBooking::query()->where('instructor_id', $mentor->id)->whereIn('status', ['confirmed', 'completed'])->where('ends_at', '<=', $now->utc())->where('ends_at', '>', $now->subDays(14)->utc())->whereNull('tasks_sent_at')->latest('ends_at')->limit(5)->get()),
        ];
    }

    /**
     * Mentees (screen 23): all, assigned or booked; who needs help first.
     *
     * @return list<MenteeRow>
     */
    public function mentees(User $mentor, string $filter = 'all', ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $brokerage = $this->brokerage($mentor);
        $assigned = array_values(array_map(static fn (MentorAssignment $a): int => $a->agent_id, MentorAssignment::query()->where('organisation_id', $brokerage->id)->where('mentor_id', $mentor->id)->whereNull('ended_at')->get()->all()));
        $booked = $this->bookedIds($mentor, $brokerage, $now);
        $ids = match ($filter) {
            'assigned' => $assigned,
            'booked' => $booked,
            default => array_values(array_unique([...$assigned, ...$booked])),
        };
        $rows = [];

        foreach (User::query()->whereIn('id', $ids)->orderBy('name')->get() as $agent) {
            $rows[] = $this->row($mentor, $agent, in_array($agent->id, $assigned, true), $now);
        }

        $order = ['quiet' => 0, 'first_session' => 1, 'behind' => 2, 'ok' => 3];
        usort($rows, static fn (array $a, array $b): int => [$order[$a['signal']], $a['name']] <=> [$order[$b['signal']], $b['name']]);

        return $rows;
    }

    /**
     * Mentee detail (screen 24).
     *
     * @return array{mentee: MenteeRow, plan: array{week: int, weeks: int, done: int, total: int}, scorecard: list<array{key: string, done: float|int, target: float|int}>, fix: string|null, deals: array{active: int, under_contract: int, closed: int}, sessions: list<SessionRow>, goals: list<array{id: int, title: string, due_date: string|null, done: bool}>, notes: list<array{id: string, body: string, created_at: string|null}>}
     */
    public function mentee(User $mentor, int $agentId, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        [$agent, $brokerage] = $this->mustBeMentee($mentor, $agentId, $now);
        $assigned = MentorDirectory::assignment($brokerage, $agent)?->mentor_id === $mentor->id;
        $plan = $this->plans->current($agent);

        try {
            $card = $this->today->scorecard($agent, $now);
        } catch (Throwable) {
            $card = ['metrics' => [], 'fix' => null];
        }

        return [
            'mentee' => $this->row($mentor, $agent, $assigned, $now),
            'plan' => ['week' => $plan['week'], 'weeks' => $plan['weeks'], 'done' => $plan['done'], 'total' => $plan['total']],
            'scorecard' => $card['metrics'],
            'fix' => $card['fix'],
            'deals' => [
                'active' => Deal::query()->where('user_id', $agent->id)->where('stage', 'active')->count(),
                'under_contract' => Deal::query()->where('user_id', $agent->id)->where('stage', 'under_contract')->count(),
                'closed' => Deal::query()->where('user_id', $agent->id)->where('stage', 'closed')->where('closed_at', '>=', $now->subDays(90))->count(),
            ],
            'sessions' => $this->sessions(SessionBooking::query()->where('instructor_id', $mentor->id)->where('student_id', $agent->id)->whereIn('status', ['confirmed', 'completed', 'no_show'])->latest('starts_at')->limit(10)->get()),
            'goals' => array_values(Todo::query()->where('user_id', $agent->id)->where('source', 'mentor')->where('related_id', (string) $mentor->id)->latest('id')->limit(20)->get()
                ->map(static fn (Todo $t): array => ['id' => $t->id, 'title' => $t->title, 'due_date' => $t->due_date?->toDateString(), 'done' => $t->done_at !== null])->all()),
            'notes' => array_values(MenteeNote::query()->where('mentor_id', $mentor->id)->where('agent_id', $agent->id)->latest('id')->limit(50)->get()
                ->map(static fn (MenteeNote $n): array => ['id' => $n->uuid, 'body' => $n->body, 'created_at' => $n->created_at?->toIso8601String()])->all()),
        ];
    }

    /** A private note on a mentee (the mentor only). */
    public function addNote(User $mentor, int $agentId, string $body): MenteeNote
    {
        [$agent, $brokerage] = $this->mustBeMentee($mentor, $agentId, CarbonImmutable::now());

        return MenteeNote::query()->create(['uuid' => (string) Str::uuid7(), 'organisation_id' => $brokerage->id, 'mentor_id' => $mentor->id, 'agent_id' => $agent->id, 'body' => mb_substr(trim($body), 0, 2000)]);
    }

    /** Removes a note (undoable). */
    public function removeNote(User $mentor, string $uuid, bool $restore = false): void
    {
        $note = MenteeNote::withTrashed()->where('mentor_id', $mentor->id)->where('uuid', $uuid)->firstOrFail();
        $restore ? $note->restore() : $note->delete();
    }

    /** A goal set together: one of the mentee's to-dos, from their mentor. */
    public function addGoal(User $mentor, int $agentId, string $title, ?string $due): Todo
    {
        [$agent] = $this->mustBeMentee($mentor, $agentId, CarbonImmutable::now());

        if ($due !== null && CarbonImmutable::parse($due)->lt(CarbonImmutable::today())) {
            throw ValidationException::withMessages(['due_date' => __('mentoring.errors.pastGoal')]);
        }

        [$todo] = $this->todos->create($agent, ['title' => mb_substr(trim($title), 0, 200), 'due_date' => $due, 'related_type' => 'mentor', 'related_id' => (string) $mentor->id, 'related_label' => (string) $mentor->name, 'source' => 'mentor'], null);

        return $todo;
    }

    public function brokerage(User $mentor): Organisation
    {
        return $this->directory->brokerageOf($mentor, Role::Mentor) ?? throw new NotFoundHttpException;
    }

    /** @return MenteeRow */
    private function row(User $mentor, User $agent, bool $assigned, CarbonImmutable $now): array
    {
        $next = SessionBooking::query()->where('instructor_id', $mentor->id)->where('student_id', $agent->id)->where('status', 'confirmed')->where('starts_at', '>', $now->utc())->orderBy('starts_at')->value('starts_at');
        $ever = SessionBooking::query()->where('instructor_id', $mentor->id)->where('student_id', $agent->id)->whereIn('status', ['confirmed', 'completed'])->exists();
        $active = $agent->last_active_at === null ? null : CarbonImmutable::instance($agent->last_active_at);
        $signal = 'ok';

        if ($active === null || $active->lt($now->subDays(self::QUIET_DAYS))) {
            $signal = 'quiet';
        } elseif ($assigned && ! $ever) {
            $signal = 'first_session';
        } else {
            $plan = $this->plans->current($agent);
            $open = count(array_filter($plan['tasks'], static fn (array $t): bool => ! $t['done']));
            $signal = $open >= self::BEHIND ? 'behind' : 'ok';
        }

        return [
            'id' => $agent->id, 'name' => (string) $agent->name, 'goal' => $this->modes->goalOf($agent), 'assigned' => $assigned, 'signal' => $signal,
            'last_active' => $active?->toIso8601String(), 'next_session' => is_string($next) || $next instanceof \DateTimeInterface ? CarbonImmutable::parse($next)->toIso8601String() : null,
        ];
    }

    /** @return list<int> */
    private function bookedIds(User $mentor, Organisation $brokerage, CarbonImmutable $now): array
    {
        $agents = $this->directory->agentsOf($brokerage);

        $students = array_map(static fn (SessionBooking $b): int => $b->student_id, SessionBooking::query()->where('instructor_id', $mentor->id)->whereIn('status', ['confirmed', 'completed'])->where('starts_at', '>=', $now->subDays(90)->utc())->get(['student_id'])->all());

        return array_values(array_unique(array_intersect($students, $agents)));
    }

    /** @return array{0: User, 1: Organisation} */
    private function mustBeMentee(User $mentor, int $agentId, CarbonImmutable $now): array
    {
        $brokerage = $this->brokerage($mentor);
        $assigned = MentorAssignment::query()->where('organisation_id', $brokerage->id)->where('mentor_id', $mentor->id)->where('agent_id', $agentId)->whereNull('ended_at')->exists();

        if (! $assigned && ! in_array($agentId, $this->bookedIds($mentor, $brokerage, $now), true)) {
            throw new NotFoundHttpException;
        }

        return [User::query()->findOrFail($agentId), $brokerage];
    }

    /**
     * @param  iterable<SessionBooking>  $bookings
     * @return list<SessionRow>
     */
    private function sessions(iterable $bookings): array
    {
        $rows = [];

        foreach ($bookings as $b) {
            $rows[] = [
                'uuid' => $b->uuid, 'agent' => (string) $b->student->name, 'agent_id' => $b->student_id, 'starts_at' => $b->starts_at->toIso8601ZuluString(), 'minutes' => $b->minutes,
                'topic' => $b->topic, 'status' => $b->status, 'session' => $b->session?->uuid, 'summary' => $b->summary, 'tasks_sent' => $b->tasks_sent_at !== null,
            ];
        }

        return $rows;
    }
}
