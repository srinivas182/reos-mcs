<?php

declare(strict_types=1);

namespace App\Brokerage;

use App\Agents\Activity;
use App\Agents\AgentModes;
use App\Agents\AgentPlans;
use App\Authorization\Role;
use App\Models\Deal;
use App\Models\DealDate;
use App\Models\Licence;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Services\StateRules\CeRequirements;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Agent oversight (A16.1, ADR 0076): which agents need help and why, which
 * deals are at risk, and the agent's profile as their broker sees it.
 *
 * - **Signals from the agent's own work:** quiet (no sign-in, conversation
 *   or appointment for the brokerage's quiet days), no deal for its
 *   no-deal days, CE or SAE behind for the time left, a plan behind, a
 *   deadline missed, and new agents in their first days.
 * - **Deals at risk:** a key date within 72 hours still open, a date
 *   already past, or a deal in one stage for 30 days.
 * - **Privacy rules:** the broker sees counts and stages, never clients,
 *   contacts, parties or notes. A deal's address shows only once it's under
 *   contract, when the broker supervises it. Agents can see exactly what
 *   their broker sees ("What my broker sees").
 * - **Scope:** the designated broker and office admins see the brokerage
 *   and its teams; a team leader sees their own team.
 *
 * @phpstan-type Signal 'quiet'|'no_deal'|'ce_behind'|'plan_behind'|'deadline_missed'|'new'
 * @phpstan-type AgentRow array{id: int, name: string, team: string|null, goal: string, last_active: string|null, signals: list<Signal>, deals: array{active: int, under_contract: int, closed_90: int}, ce: array{due_on: string|null, hours_left: float, first_renewal: bool, complete: bool}|null}
 * @phpstan-type RiskRow array{id: string, agent: string, agent_id: int, stage: string, city: string|null, address: string|null, reasons: list<'date_soon'|'date_missed'|'stuck'>, next_date: array{kind: string, due_on: string}|null}
 * @phpstan-type Settings array{quiet_days: int, no_deal_days: int, new_days: int}
 * @phpstan-type Board array{brokerage: string, scope: 'brokerage'|'team', team: string|null, settings: Settings, can_change: bool, counts: array{agents: int, need_help: int, deals_at_risk: int}, agents: list<AgentRow>, at_risk: list<RiskRow>}
 * @phpstan-type Profile array{agent: AgentRow, plan: array{goal: string, week: int, weeks: int, done: int, total: int}|null, activity: array{conversations: int, appointments: int, posts: int, ce_hours: float}, deals: list<array{id: string, stage: string, side: string, city: string|null, address: string|null, closed_at: string|null}>, licences: list<array{state: string, number: string, status: string, expires_on: string|null}>}
 */
final readonly class Oversight
{
    public const DEFAULTS = ['quiet_days' => 14, 'no_deal_days' => 90, 'new_days' => 90];

    /** Tasks short of where the plan's past weeks should be that make it "behind". */
    private const PLAN_BEHIND = 3;

    public function __construct(
        private BrokerageAccess $access,
        private BrokerageStaff $staff,
        private Memberships $memberships,
        private AgentModes $modes,
        private AgentPlans $plans,
        private CeRequirements $ce,
        private Tenancy $tenancy,
        private AuditLogger $audit,
    ) {}

    /** @return Settings */
    public static function settings(Organisation $brokerage): array
    {
        $saved = $brokerage->oversight_settings ?? [];
        $int = static fn (string $key): int => is_int($saved[$key] ?? null) && $saved[$key] > 0 ? $saved[$key] : self::DEFAULTS[$key];

        return ['quiet_days' => $int('quiet_days'), 'no_deal_days' => $int('no_deal_days'), 'new_days' => $int('new_days')];
    }

    /** @return Board */
    public function board(User $user, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        [$brokerage, $team] = $this->access->scope($user);
        $settings = self::settings($brokerage);
        $agents = $this->agents($brokerage, $team);
        $rows = [];

        foreach ($agents as $agent) {
            $rows[] = $this->row($agent, $brokerage, $settings, $now);
        }

        // Most signals first, then the quietest.
        usort($rows, static fn (array $a, array $b): int => [count($b['signals']), $a['last_active'] ?? ''] <=> [count($a['signals']), $b['last_active'] ?? '']);
        $risk = $this->atRisk($agents, $now);

        return [
            'brokerage' => $brokerage->name, 'scope' => $team === null ? 'brokerage' : 'team', 'team' => $team?->name, 'settings' => $settings,
            'can_change' => $team === null && $this->staff->isDesignated($brokerage, $user),
            'counts' => ['agents' => count($rows), 'need_help' => count(array_filter($rows, static fn (array $r): bool => $r['signals'] !== [])), 'deals_at_risk' => count($risk)],
            'agents' => $rows, 'at_risk' => $risk,
        ];
    }

    /**
     * One agent as their broker sees them.
     *
     * @return Profile
     */
    public function profile(User $user, int $agentId, ?CarbonImmutable $now = null): array
    {
        [$brokerage, $team] = $this->access->scope($user);
        $agent = $this->agents($brokerage, $team)->firstWhere('id', $agentId) ?? throw new NotFoundHttpException;
        $this->audit->record('oversight.profile_viewed', 'user', (string) $agent->id, [], $user, $brokerage->id);

        return $this->describe($agent, $brokerage, $now ?? CarbonImmutable::now());
    }

    /**
     * "What my broker sees" (A16.1): the agent's own profile, exactly as
     * their broker sees it.
     *
     * @return Profile|null null when the agent isn't in a brokerage
     */
    public function mine(User $agent, ?CarbonImmutable $now = null): ?array
    {
        foreach ($this->memberships->of($agent) as $membership) {
            if ($membership->role !== Role::Agent) {
                continue;
            }

            $org = $membership->organisation;
            $brokerage = $org->parent !== null && $org->type->value === 'team' ? $org->parent : $org;

            if ($brokerage->type->value === 'brokerage') {
                return $this->describe($agent, $brokerage, $now ?? CarbonImmutable::now());
            }
        }

        return null;
    }

    /**
     * The brokerage's own thresholds. The designated broker only.
     *
     * @param  Settings  $settings
     * @return Board
     */
    public function setSettings(User $user, array $settings): array
    {
        [$brokerage, $team] = $this->access->scope($user);

        if ($team !== null) {
            throw new NotFoundHttpException;
        }

        $this->staff->designatedOnly($brokerage, $user);
        $before = self::settings($brokerage);
        $brokerage->oversight_settings = $settings;
        $brokerage->save();
        $this->audit->record('oversight.settings', 'organisation', (string) $brokerage->id, ['before' => $before, 'after' => $settings], $user, $brokerage->id);

        return $this->board($user);
    }

    /** @return Collection<int, User> */
    public function agents(Organisation $brokerage, ?Organisation $team): Collection
    {
        return $team === null ? $this->memberships->holdersInTree(Role::Agent, $brokerage) : $this->memberships->holdersIn(Role::Agent, $team);
    }

    /**
     * @param  Settings  $settings
     * @return AgentRow
     */
    private function row(User $agent, Organisation $brokerage, array $settings, CarbonImmutable $now): array
    {
        $deals = $this->deals($agent);
        $active = $agent->last_active_at === null ? null : CarbonImmutable::instance($agent->last_active_at);
        $quietFrom = $now->subDays($settings['quiet_days']);
        $worked = Activity::conversations($agent, $quietFrom, $now) + Activity::appointments($agent, $quietFrom, $now);
        $licence = $this->licence($agent, $brokerage);
        $ce = $licence === null ? null : $this->ce->for($licence, $now->startOfDay());
        $signals = [];

        if (($active === null || $active->lt($quietFrom)) && $worked === 0) {
            $signals[] = 'quiet';
        }

        $recent = $deals->contains(static fn (Deal $d): bool => in_array($d->stage, ['active', 'under_contract'], true) || ($d->created_at !== null && $d->created_at->gte($now->subDays($settings['no_deal_days']))) || ($d->closed_at !== null && $d->closed_at->gte($now->subDays($settings['no_deal_days']))));

        if (! $recent) {
            $signals[] = 'no_deal';
        }

        if ($ce !== null && ! $ce['complete'] && $ce['days_left'] !== null && self::ceBehind($ce['hours_left'], $ce['days_left'], $ce['first_renewal'])) {
            $signals[] = 'ce_behind';
        }

        $plan = $this->plans->current($agent);

        // Behind: fewer tasks done than the plan's past weeks call for (this week's are still on time).
        $expected = $plan['weeks'] > 0 ? (int) floor($plan['total'] * ($plan['week'] - 1) / $plan['weeks']) : 0;

        if ($expected - $plan['done'] >= self::PLAN_BEHIND) {
            $signals[] = 'plan_behind';
        }

        $open = $deals->filter(static fn (Deal $d): bool => in_array($d->stage, ['active', 'under_contract'], true))->pluck('id')->all();

        if ($open !== [] && $this->tenancy->asPlatform(static fn (): bool => DealDate::query()->whereIn('deal_id', $open)->whereNull('done_at')->where('due_on', '<', $now->toDateString())->exists())) {
            $signals[] = 'deadline_missed';
        }

        $since = $licence === null ? $agent->created_at : ($licence->licensed_on ?? $agent->created_at);

        if ($since !== null && CarbonImmutable::instance($since)->gte($now->subDays($settings['new_days']))) {
            $signals[] = 'new';
        }

        return [
            'id' => $agent->id, 'name' => (string) $agent->name, 'team' => $this->teamOf($agent, $brokerage), 'goal' => $this->modes->goalOf($agent),
            'last_active' => $active?->toIso8601String(), 'signals' => $signals,
            'deals' => [
                'active' => $deals->where('stage', 'active')->count(), 'under_contract' => $deals->where('stage', 'under_contract')->count(),
                'closed_90' => $deals->filter(static fn (Deal $d): bool => $d->stage === 'closed' && $d->closed_at !== null && $d->closed_at->gte($now->subDays(90)))->count(),
            ],
            'ce' => $ce === null ? null : ['due_on' => $ce['due_on'], 'hours_left' => $ce['hours_left'], 'first_renewal' => $ce['first_renewal'], 'complete' => $ce['complete']],
        ];
    }

    /**
     * Behind for the time left: SAE needs its hours across the whole first
     * period, so an agent is behind with more than 30 hours left inside six
     * months; CE with any hours left inside 60 days.
     */
    public static function ceBehind(float $hoursLeft, int $daysLeft, bool $firstRenewal): bool
    {
        return $hoursLeft > 0 && ($firstRenewal ? ($daysLeft <= 180 && $hoursLeft > 30) || $daysLeft <= 60 : $daysLeft <= 60);
    }

    /**
     * @param  Collection<int, User>  $agents
     * @return list<RiskRow>
     */
    private function atRisk(Collection $agents, CarbonImmutable $now): array
    {
        $byId = $agents->keyBy('id');
        $rows = [];
        $deals = $this->tenancy->asPlatform(static fn (): Collection => Deal::query()->whereIn('user_id', $agents->pluck('id'))->whereIn('stage', ['active', 'under_contract'])
            ->with(['dates' => static fn ($q) => $q->whereNull('done_at')->orderBy('due_on')])->get());

        foreach ($deals as $deal) {
            $reasons = [];
            $next = $deal->dates->first();

            if ($deal->dates->contains(static fn (DealDate $d): bool => $d->due_on->lt($now->startOfDay()))) {
                $reasons[] = 'date_missed';
            }

            if ($deal->dates->contains(static fn (DealDate $d): bool => $d->due_on->gte($now->startOfDay()) && $d->due_on->lte($now->addHours(72)))) {
                $reasons[] = 'date_soon';
            }

            $moved = $deal->source_updated_at ?? $deal->updated_at;

            if ($moved !== null && $moved->lt($now->subDays(30))) {
                $reasons[] = 'stuck';
            }

            if ($reasons === []) {
                continue;
            }

            $rows[] = [
                'id' => $deal->uuid, 'agent' => (string) ($byId[$deal->user_id]->name ?? ''), 'agent_id' => $deal->user_id, 'stage' => $deal->stage, 'city' => $deal->city,
                // The address only for deals the broker supervises (decision 1).
                'address' => $deal->stage === 'under_contract' ? $deal->property : null, 'reasons' => $reasons,
                'next_date' => $next === null ? null : ['kind' => $next->kind, 'due_on' => $next->due_on->toDateString()],
            ];
        }

        usort($rows, static fn (array $a, array $b): int => ($a['next_date']['due_on'] ?? '9999') <=> ($b['next_date']['due_on'] ?? '9999'));

        return $rows;
    }

    /** @return Profile */
    private function describe(User $agent, Organisation $brokerage, CarbonImmutable $now): array
    {
        $plan = $this->plans->current($agent);
        $month = $now->startOfMonth();

        return [
            'agent' => $this->row($agent, $brokerage, self::settings($brokerage), $now),
            'plan' => ['goal' => $plan['goal'], 'week' => $plan['week'], 'weeks' => $plan['weeks'], 'done' => $plan['done'], 'total' => $plan['total']],
            'activity' => [
                'conversations' => Activity::conversations($agent, $month, $now), 'appointments' => Activity::appointments($agent, $month, $now),
                'posts' => Activity::posts($agent, $month, $now), 'ce_hours' => Activity::ceHours($agent, $month, $now),
            ],
            // Counts and stages; the address only under contract; never parties, contacts or notes.
            'deals' => array_values($this->deals($agent)->sortByDesc('updated_at')->take(20)->map(static fn (Deal $d): array => [
                'id' => $d->uuid, 'stage' => $d->stage, 'side' => $d->side, 'city' => $d->city,
                'address' => $d->stage === 'under_contract' ? $d->property : null, 'closed_at' => $d->closed_at?->toDateString(),
            ])->all()),
            'licences' => array_values(Licence::query()->where('user_id', $agent->id)->orderBy('state_code')->get()->map(static fn (Licence $l): array => [
                'state' => $l->state_code, 'number' => $l->number, 'status' => $l->status->value, 'expires_on' => $l->expires_on?->toDateString(),
            ])->all()),
        ];
    }

    /** @return Collection<int, Deal> */
    private function deals(User $agent): Collection
    {
        return $this->tenancy->asPlatform(static fn (): Collection => Deal::query()->where('user_id', $agent->id)->get(['id', 'uuid', 'user_id', 'stage', 'side', 'city', 'property', 'closed_at', 'created_at', 'updated_at', 'source_updated_at']));
    }

    private function licence(User $agent, Organisation $brokerage): ?Licence
    {
        $state = ($brokerage->states ?? [])[0] ?? 'TX';

        return Licence::query()->where('user_id', $agent->id)->where('state_code', $state)->first() ?? Licence::query()->where('user_id', $agent->id)->first();
    }

    /** The team an agent is on inside the brokerage, or null. */
    public function teamOf(User $agent, Organisation $brokerage): ?string
    {
        foreach ($this->memberships->of($agent) as $m) {
            if ($m->role === Role::Agent && $m->organisation->parent_id === $brokerage->id) {
                return $m->organisation->name;
            }
        }

        return null;
    }
}
