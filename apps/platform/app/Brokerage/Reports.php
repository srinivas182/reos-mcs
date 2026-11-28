<?php

declare(strict_types=1);

namespace App\Brokerage;

use App\Integrations\Catalogue;
use App\Integrations\IntegrationDefinition;
use App\Models\Connection;
use App\Models\Course;
use App\Models\Deal;
use App\Models\User;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Brokerage reports (A16.3, ADR 0078): closings and volume by agent, team
 * and month, matched to the transaction tool; and training progress.
 *
 * - **Closings** are the agents' deals closed in the period. A deal synced
 *   from a transaction tool (SkySlope, dotloop, Lone Wolf…) counts under
 *   that tool, with when it last synced; a deal closed only in REOS is a
 *   **difference** the broker sees by name, so the books can be squared
 *   with the tool the brokerage pays commissions from.
 * - **Training** is each course the brokerage assigned: how many agents,
 *   done, overdue, and the CE hours it carries.
 *
 * A team leader sees their team only (BrokerageAccess::scope).
 *
 * @phpstan-type Period 'this_month'|'last_month'|'this_quarter'|'this_year'|'last_year'
 * @phpstan-type Line array{count: int, volume: int}
 * @phpstan-type Closings array{brokerage: string, scope: 'brokerage'|'team', team: string|null, period: Period, from: string, to: string, totals: Line, by_agent: list<array{id: int, name: string, team: string|null, count: int, volume: int}>, by_team: list<array{name: string|null, count: int, volume: int}>, by_month: list<array{month: string, count: int, volume: int}>, tools: list<array{connection: int, tool: string, name: string, label: string|null, last_synced_at: string|null, stale: bool, count: int, volume: int}>, reos_only: array{count: int, volume: int, deals: list<array{id: string, agent: string, property: string, price: int|null, closed_at: string}>}}
 * @phpstan-type Training array{brokerage: string, scope: 'brokerage'|'team', team: string|null, courses: list<array{id: int, title: string, type: string, ce_hours: string|null, assigned: int, done: int, overdue: int}>}
 */
final readonly class Reports
{
    public const PERIODS = ['this_month', 'last_month', 'this_quarter', 'this_year', 'last_year'];

    /** A tool not synced for longer than this is marked, since its numbers may be behind. */
    public const STALE_HOURS = 24;

    public function __construct(
        private BrokerageAccess $access,
        private Oversight $oversight,
        private Tenancy $tenancy,
        private Catalogue $catalogue,
    ) {}

    /**
     * @param  Period  $period
     * @return Closings
     */
    public function closings(User $user, string $period = 'this_year', ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        [$brokerage, $team] = $this->access->scope($user);
        [$from, $to] = self::range($period, $now);
        $agents = $this->oversight->agents($brokerage, $team)->keyBy('id');
        $deals = $this->tenancy->asPlatform(static fn () => Deal::query()->whereIn('user_id', $agents->keys())->where('stage', 'closed')
            ->whereBetween('closed_at', [$from, $to])->orderBy('closed_at')->limit(5000)
            ->get(['uuid', 'user_id', 'connection_id', 'source', 'property', 'price', 'closed_at']));

        $teams = [];
        $byAgent = [];
        $byTeam = [];
        $byMonth = [];
        $byTool = [];
        $reosOnly = [];

        foreach ($deals as $deal) {
            $agent = $agents->get($deal->user_id);

            if (! $agent instanceof User || $deal->closed_at === null) {
                continue;
            }

            $teams[$agent->id] ??= $this->oversight->teamOf($agent, $brokerage);
            $price = $deal->price ?? 0;
            self::add($byAgent, $agent->id, $price);
            self::add($byTeam, $teams[$agent->id] ?? '', $price);
            self::add($byMonth, $deal->closed_at->format('Y-m'), $price);

            if ($deal->source !== 'reos' && $deal->connection_id !== null) {
                self::add($byTool, $deal->connection_id, $price);
            } else {
                $reosOnly[] = ['id' => $deal->uuid, 'agent' => (string) $agent->name, 'property' => $deal->property, 'price' => $deal->price, 'closed_at' => $deal->closed_at->toIso8601String()];
            }
        }

        $toolIds = $this->tools();
        // Every connected tool shows, with nothing closed if that's so: "SkySlope: 0" is a match too.
        $connections = $this->tenancy->asPlatform(static fn () => Connection::query()->where(static fn ($q) => $q
            ->where(static fn ($o) => $o->where('owner_type', Connection::OWNER_ORGANISATION)->whereIn('owner_id', array_filter([$brokerage->id, $team?->id])))
            ->orWhereIn('id', array_keys($byTool)))
            ->whereIn('integration_id', $toolIds)->get(['id', 'integration_id', 'account_label', 'last_synced_at']));
        $tools = [];

        foreach ($connections as $c) {
            $line = $byTool[$c->id] ?? ['count' => 0, 'volume' => 0];
            $tools[] = [
                'connection' => $c->id, 'tool' => $c->integration_id, 'name' => $this->catalogue->find($c->integration_id)->name ?? $c->integration_id, 'label' => $c->account_label, 'last_synced_at' => $c->last_synced_at?->toIso8601String(),
                'stale' => $c->last_synced_at === null || $c->last_synced_at->lt($now->subHours(self::STALE_HOURS)), ...$line,
            ];
        }

        $agentRows = [];

        foreach ($byAgent as $id => $line) {
            $agent = $agents->get($id);
            $agentRows[] = ['id' => (int) $id, 'name' => $agent instanceof User ? (string) $agent->name : '', 'team' => $teams[(int) $id] ?? null, ...$line];
        }

        usort($agentRows, static fn (array $a, array $b): int => [$b['volume'], $a['name']] <=> [$a['volume'], $b['name']]);
        $teamRows = [];

        foreach ($byTeam as $name => $line) {
            $teamRows[] = ['name' => $name === '' ? null : (string) $name, ...$line];
        }

        usort($teamRows, static fn (array $a, array $b): int => $b['volume'] <=> $a['volume']);
        $monthRows = [];

        for ($m = CarbonImmutable::instance($from)->startOfMonth(); $m->lte($to); $m = $m->addMonth()) {
            $monthRows[] = ['month' => $m->format('Y-m'), ...$byMonth[$m->format('Y-m')] ?? ['count' => 0, 'volume' => 0]];
        }

        return [
            'brokerage' => $brokerage->name, 'scope' => $team === null ? 'brokerage' : 'team', 'team' => $team?->name,
            'period' => $period, 'from' => $from->toDateString(), 'to' => $to->toDateString(),
            'totals' => ['count' => count($deals), 'volume' => (int) $deals->sum(static fn (Deal $d): int => $d->price ?? 0)],
            'by_agent' => $agentRows, 'by_team' => $teamRows, 'by_month' => $monthRows, 'tools' => $tools,
            'reos_only' => ['count' => count($reosOnly), 'volume' => array_sum(array_map(static fn (array $d): int => $d['price'] ?? 0, $reosOnly)), 'deals' => $reosOnly],
        ];
    }

    /** @return Training */
    public function training(User $user, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        [$brokerage, $team] = $this->access->scope($user);
        $agents = $this->oversight->agents($brokerage, $team)->pluck('id');
        $rows = $this->tenancy->run($brokerage->id, static fn () => DB::table('course_assignments')->where('organisation_id', $brokerage->id)->whereIn('user_id', $agents)
            ->groupBy('course_id')->selectRaw('course_id, count(*) as assigned, count(completed_at) as done, sum(case when completed_at is null and due_on < ? then 1 else 0 end) as overdue', [$today->toDateString()])->get());
        $courses = Course::query()->whereIn('id', $rows->pluck('course_id'))->get(['id', 'title', 'type', 'hours'])->keyBy('id');
        $out = [];

        foreach ($rows as $r) {
            $course = $courses->get((int) $r->course_id);

            if (! $course instanceof Course) {
                continue;
            }

            $out[] = [
                'id' => $course->id, 'title' => $course->title, 'type' => $course->type->value, 'ce_hours' => $course->type->value === 'ce' ? $course->hours : null,
                'assigned' => (int) $r->assigned, 'done' => (int) $r->done, 'overdue' => (int) $r->overdue,
            ];
        }

        usort($out, static fn (array $a, array $b): int => [$b['overdue'], $a['title']] <=> [$a['overdue'], $b['title']]);

        return ['brokerage' => $brokerage->name, 'scope' => $team === null ? 'brokerage' : 'team', 'team' => $team?->name, 'courses' => $out];
    }

    /**
     * Closings this month, for the broker's brief.
     *
     * @return Line
     */
    public function monthToDate(User $user, ?CarbonImmutable $now = null): array
    {
        $r = $this->closings($user, 'this_month', $now);

        return $r['totals'];
    }

    /**
     * @param  Period  $period
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function range(string $period, CarbonImmutable $now): array
    {
        return match ($period) {
            'this_month' => [$now->startOfMonth(), $now->endOfMonth()],
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$now->startOfQuarter(), $now->endOfQuarter()],
            'last_year' => [$now->subYear()->startOfYear(), $now->subYear()->endOfYear()],
            default => [$now->startOfYear(), $now->endOfYear()],
        };
    }

    /**
     * The transaction tools, from the integrations register.
     *
     * @return list<string>
     */
    private function tools(): array
    {
        return array_values(array_map(static fn (IntegrationDefinition $d): string => $d->id, array_filter($this->catalogue->all(), static fn (IntegrationDefinition $d): bool => $d->category === 'transactions')));
    }

    /**
     * @template K of array-key
     *
     * @param  array<K, array{count: int, volume: int}>  $lines
     * @param  K  $key
     */
    private static function add(array &$lines, int|string $key, int $price): void
    {
        $lines[$key] ??= ['count' => 0, 'volume' => 0];
        $lines[$key]['count']++;
        $lines[$key]['volume'] += $price;
    }
}
