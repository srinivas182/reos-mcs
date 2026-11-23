<?php

declare(strict_types=1);

namespace App\Agents;

use App\Integrations\Catalogue;
use App\Models\CalendarEvent;
use App\Models\CeCompletion;
use App\Models\Connection;
use App\Models\Contact;
use App\Models\ContactActivity;
use App\Models\ContactNote;
use App\Models\Deal;
use App\Models\DealDate;
use App\Models\Todo;
use App\Models\User;
use App\Services\Calendar\CalendarEvents;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Today for agents (A11.1b, ADR 0044; agent screens 14, 15 and 110):
 *
 * - **One ranked list** from every tool: overdue first, then deal deadlines
 *   within 72 hours, today's appointments, CRM follow-ups and to-dos due
 *   today, then this week's plan tasks. Each item names its source; done in
 *   REOS is done at the source where the tool allows it (B7.2).
 * - **Mode stats** at the top: Launch counts the week and the targets;
 *   Grow the pipeline; Lead and Own closings and deals.
 * - **Day one** (screen 15): until 20 contacts or a connected tool, three
 *   first steps instead of empty lists.
 * - **Weekly scorecard** (110): conversations, appointments, clients and CE
 *   against the plan's targets, and the one fix.
 *
 * @phpstan-type TodayItem array{key: string, kind: 'todo'|'deadline'|'event'|'plan', reason: 'overdue'|'deadline'|'appointment'|'follow_up'|'plan', title: string, detail: string|null, source: string, at: string|null, all_day: bool, todo_id: int|null, deal: string|null, plan: array{code: string, week: int}|null}
 * @phpstan-type Scorecard array{week_of: string, metrics: list<array{key: string, done: float|int, target: float|int}>, fix: string|null}
 * @phpstan-type Today array{goal: string, stats: list<array{key: string, value: int, target: float|int|null}>, items: list<TodayItem>, more: int, day_one: list<array{code: string, minutes: int, done: bool, link: string}>|null, plan_week: int}
 */
final readonly class AgentToday
{
    /** Shown at once; the rest behind "More". */
    public const SHOWN = 6;

    private const RANKS = ['overdue' => 0, 'deadline' => 1, 'appointment' => 2, 'follow_up' => 3, 'plan' => 4];

    public function __construct(
        private AgentModes $modes,
        private AgentPlans $plans,
        private CalendarEvents $events,
        private Catalogue $catalogue,
    ) {}

    /** @return Today */
    public function for(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $this->modes->show($user);
        $goal = $this->modes->goalOf($user);
        $items = $this->ranked($user, $from, $to);

        return [
            'goal' => $goal,
            'stats' => $this->stats($user, $goal, $items),
            'items' => array_slice($items, 0, self::SHOWN),
            'more' => max(0, count($items) - self::SHOWN),
            'day_one' => $this->dayOne($user),
            'plan_week' => $this->plans->week($this->plans->plan($user)),
        ];
    }

    /**
     * Every item for the day, ranked (the morning brief reads them all, A11.1c).
     *
     * @return list<TodayItem>
     */
    public function ranked(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $items = [...$this->todos($user, $from, $to), ...$this->appointments($user, $from, $to), ...$this->planTasks($user)];

        usort($items, static fn (array $a, array $b): int => [self::RANKS[$a['reason']], $a['at'] ?? '9999'] <=> [self::RANKS[$b['reason']], $b['at'] ?? '9999']);

        return $items;
    }

    /** @return Scorecard */
    public function scorecard(User $user, CarbonImmutable $now): array
    {
        $this->modes->show($user);
        $monday = $now->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
        $sunday = $monday->addWeek();
        $targets = $this->plans->targets($user, $this->modes->goalOf($user));
        $done = [
            'conversations' => $this->conversations($user, $monday, $sunday),
            'appointments' => CalendarEvent::query()->where('user_id', $user->id)->whereIn('kind', ['appointment', 'showing'])->where('status', '!=', 'cancelled')
                ->whereBetween('starts_at', [$monday->utc(), $sunday->utc()])->count(),
            'clients' => Deal::query()->where('user_id', $user->id)->whereIn('stage', ['active', 'under_contract'])->count(),
            'ce_hours' => (float) CeCompletion::query()->where('user_id', $user->id)->whereBetween('completed_on', [$monday->toDateString(), $sunday->subDay()->toDateString()])->sum('hours'),
        ];
        $metrics = [];
        $fix = null;
        $worst = 1.0;

        foreach (['conversations', 'appointments', 'clients', 'ce_hours'] as $key) {
            $target = $targets[$key] ?? 0;
            $metrics[] = ['key' => $key, 'done' => $done[$key], 'target' => $target];
            $share = $target > 0 ? $done[$key] / $target : 1.0;

            if ($share < $worst) {
                [$worst, $fix] = [$share, $key];
            }
        }

        return ['week_of' => $monday->toDateString(), 'metrics' => $metrics, 'fix' => $fix];
    }

    /** @return list<TodayItem> */
    private function todos(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $today = $from->toDateString();
        $soon = $from->addHours(72)->toDateString();
        $todos = Todo::query()->where('user_id', $user->id)->whereNull('done_at')
            ->where(static fn ($q) => $q->where('due_date', '<=', $soon)->orWhere('due_at', '<', $from->addHours(72)->utc())->orWhere(static fn ($n) => $n->whereNull('due_date')->whereNull('due_at')->where('source', 'crm')))
            ->orderBy('id')->limit(100)->get();
        $deadlines = DealDate::query()->whereIn('todo_id', $todos->pluck('id')->all())->with('deal')->get()->keyBy('todo_id');
        $crm = DB::table('contact_tasks')->whereIn('todo_id', $todos->pluck('id')->all())->pluck('source', 'todo_id')->all();
        $items = [];

        foreach ($todos as $todo) {
            $due = $todo->due_at !== null ? CarbonImmutable::instance($todo->due_at) : ($todo->due_date !== null ? CarbonImmutable::parse($todo->due_date->toDateString(), $from->tz) : null);
            $dueDay = $due?->setTimezone($from->tz)->toDateString();
            $deadline = $deadlines->get($todo->id);
            $overdue = $due !== null && ($todo->due_at !== null ? $due->lt(now()) : $dueDay < $today);

            $reason = match (true) {
                $overdue => 'overdue',
                $deadline !== null => 'deadline',
                $dueDay === null || $dueDay === $today => 'follow_up',
                default => null,
            };

            if ($reason === null) {
                continue;
            }

            $source = $deadline !== null ? $this->name($deadline->deal->source) : (isset($crm[$todo->id]) && is_string($crm[$todo->id]) ? $this->name($crm[$todo->id]) : 'REOS');
            $items[] = [
                'key' => "todo-{$todo->id}",
                'kind' => $deadline !== null ? 'deadline' : 'todo',
                'reason' => $reason,
                'title' => $todo->title,
                'detail' => $deadline?->deal->property ?? $todo->related_label,
                'source' => $source,
                'at' => $due?->toIso8601String(),
                'all_day' => $todo->due_at === null,
                'todo_id' => $todo->id,
                'deal' => $deadline?->deal->uuid,
                'plan' => null,
            ];
        }

        return $items;
    }

    /** @return list<TodayItem> */
    private function appointments(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $items = [];

        foreach ($this->events->between($user, $from, $to) as $event) {
            if ($event->all_day || $event->kind === 'todo' || $event->kind === 'deadline' || $event->ends_at->lt(now())) {
                continue;
            }

            $items[] = [
                'key' => "event-{$event->id}",
                'kind' => 'event',
                'reason' => 'appointment',
                'title' => $event->title ?? __('agents.today.busy'),
                'detail' => $event->location,
                'source' => $event->source === CalendarEvent::SOURCE_REOS ? 'REOS' : $this->name($event->source),
                'at' => CarbonImmutable::instance($event->starts_at)->toIso8601String(),
                'all_day' => false,
                'todo_id' => null,
                'deal' => null,
                'plan' => null,
            ];
        }

        return $items;
    }

    /** @return list<TodayItem> */
    private function planTasks(User $user): array
    {
        $items = [];

        foreach ($this->plans->current($user)['tasks'] as $task) {
            if ($task['done']) {
                continue;
            }

            $items[] = [
                'key' => "plan-{$task['code']}-{$task['week']}",
                'kind' => 'plan',
                'reason' => 'plan',
                'title' => self::line("agents.plan.tasks.{$task['code']}"),
                'detail' => null,
                'source' => self::line('agents.today.planSource'),
                'at' => null,
                'all_day' => true,
                'todo_id' => null,
                'deal' => null,
                'plan' => ['code' => $task['code'], 'week' => $task['week']],
            ];
        }

        return $items;
    }

    /**
     * @param  list<TodayItem>  $items
     * @return list<array{key: string, value: int, target: float|int|null}>
     */
    private function stats(User $user, string $goal, array $items): array
    {
        $now = CarbonImmutable::now();

        if ($goal === 'launch') {
            $card = $this->scorecard($user, $now);
            $by = array_column($card['metrics'], null, 'key');

            return [
                ['key' => 'week', 'value' => $this->plans->week($this->plans->plan($user)), 'target' => AgentPlans::WEEKS],
                ['key' => 'conversations', 'value' => (int) $by['conversations']['done'], 'target' => $by['conversations']['target']],
                ['key' => 'appointments', 'value' => (int) $by['appointments']['done'], 'target' => $by['appointments']['target']],
            ];
        }

        $active = Deal::query()->where('user_id', $user->id)->where('stage', 'active')->count();
        $contract = Deal::query()->where('user_id', $user->id)->where('stage', 'under_contract')->count();

        if ($goal === 'grow') {
            $hot = count(array_filter($items, static fn (array $i): bool => in_array($i['reason'], ['overdue', 'deadline', 'follow_up'], true)));

            return [
                ['key' => 'pipeline', 'value' => $active + $contract, 'target' => null],
                ['key' => 'under_contract', 'value' => $contract, 'target' => null],
                ['key' => 'hot_today', 'value' => $hot, 'target' => null],
            ];
        }

        return [
            ['key' => 'closings_12m', 'value' => $this->modes->verifiedClosings($user, $now->subYear()), 'target' => null],
            ['key' => 'pipeline', 'value' => $active + $contract, 'target' => null],
            ['key' => 'under_contract', 'value' => $contract, 'target' => null],
        ];
    }

    /** @return list<array{code: string, minutes: int, done: bool, link: string}>|null */
    private function dayOne(User $user): ?array
    {
        $contacts = Contact::query()->where('owner_type', 'user')->where('owner_id', $user->id)->count();
        $connected = Connection::query()->where('owner_type', Connection::OWNER_USER)->where('owner_id', $user->id)->exists();

        if ($contacts >= 20 || $connected) {
            return null;
        }

        $plan = $this->plans->current($user);
        $announced = (bool) array_filter($plan['tasks'], static fn (array $t): bool => $t['code'] === 'announce_licence' && $t['done']);

        return [
            ['code' => 'import_contacts', 'minutes' => 5, 'done' => false, 'link' => 'contacts'],
            ['code' => 'announce_licence', 'minutes' => 2, 'done' => $announced, 'link' => 'announce'],
            ['code' => 'connect_tools', 'minutes' => 3, 'done' => false, 'link' => 'connections'],
        ];
    }

    /** Calls and emails from the CRMs, and touches logged in REOS, this week. */
    private function conversations(User $user, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $contacts = Contact::query()->where('owner_type', 'user')->where('owner_id', $user->id)->select('id');

        return ContactActivity::query()->whereIn('contact_id', $contacts)->whereIn('kind', ['call', 'email', 'text'])->whereBetween('occurred_at', [$from->utc(), $to->utc()])->count()
            + ContactNote::query()->where('author_id', $user->id)->whereBetween('created_at', [$from->utc(), $to->utc()])->count();
    }

    private function name(string $source): string
    {
        return $source === 'reos' ? 'REOS' : ($this->catalogue->find($source)->name ?? $source);
    }

    private static function line(string $key): string
    {
        $line = __($key);

        return is_string($line) ? $line : $key;
    }
}
