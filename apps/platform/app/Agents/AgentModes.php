<?php

declare(strict_types=1);

namespace App\Agents;

use App\Authorization\Role;
use App\Enums\LicenceStatus;
use App\Enums\LicenceType;
use App\Models\AgentGoalChange;
use App\Models\AgentProfile;
use App\Models\Deal;
use App\Models\Licence;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\StateRules\StateRules;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Agent modes (A11.1a, ADR 0044): one Agent membership, four goals.
 *
 * - **Launch** (first closing), **Grow** (more deals), **Lead** (build a
 *   team) and **Own** (open a brokerage) decide what the home screen shows
 *   first; every feature stays reachable in every mode.
 * - **Own** unlocks at the state's years licensed (Texas: 4, TREC's broker
 *   experience rule) or with a broker licence.
 * - **Suggested switches** come from verified closings (deals from a
 *   transaction tool): the first suggests Grow; the state's count in 12
 *   months suggests Lead. The agent decides; "Not yet" waits 30 days.
 * - **Flags** tune any mode: part-time halves the plan's targets.
 *
 * @phpstan-type ModeState array{goal: string|null, flags: list<string>, needs_setup: bool, suggested_default: string, own: array{unlocked: bool, unlocks_on: string|null, years: int}, suggestion: array{goal: string, closings: int}|null, history: list<array{from: string|null, to: string, reason: string, at: string}>, welcome: array{name: string, licence: string|null, state: string|null, brokerage: string|null, verified: bool}}
 */
final readonly class AgentModes
{
    public const GOALS = ['launch', 'grow', 'lead', 'own'];

    public const FLAGS = ['part_time', 'bilingual', 'specialist', 'referral_only', 'returning'];

    public const SNOOZE_DAYS = 30;

    public function __construct(private ActiveRole $roles, private StateRules $rules) {}

    /** @return ModeState */
    public function show(User $user): array
    {
        $this->allow($user);
        $profile = $this->profile($user);
        $licence = $this->licence($user);
        $own = $this->own($user, $licence);

        return [
            'goal' => $profile->goal,
            'flags' => $profile->flags,
            'needs_setup' => $profile->goal === null,
            'suggested_default' => $this->defaultGoal($user, $licence),
            'own' => $own,
            'suggestion' => $this->suggestion($user, $profile, $licence),
            'history' => array_values(AgentGoalChange::query()->where('user_id', $user->id)->latest('id')->limit(20)->get()
                ->map(static fn (AgentGoalChange $c): array => ['from' => $c->from_goal, 'to' => $c->to_goal, 'reason' => $c->reason, 'at' => $c->created_at->toIso8601String()])->all()),
            'welcome' => [
                'name' => (string) $user->name,
                'licence' => $licence?->number,
                'state' => $licence?->state_code,
                'brokerage' => $licence?->sponsor_name,
                'verified' => $licence !== null && in_array($licence->status, [LicenceStatus::Active, LicenceStatus::Inactive], true),
            ],
        ];
    }

    /**
     * @param  'setup'|'manual'|'suggested'  $reason
     * @return ModeState
     */
    public function choose(User $user, string $goal, string $reason): array
    {
        $this->allow($user);

        if (! in_array($goal, self::GOALS, true)) {
            throw ValidationException::withMessages(['goal' => __('agents.modes.errors.goal')]);
        }

        if ($goal === 'own' && ! $this->own($user, $this->licence($user))['unlocked']) {
            throw ValidationException::withMessages(['goal' => __('agents.modes.errors.ownLocked')]);
        }

        $profile = $this->profile($user);

        DB::transaction(function () use ($user, $profile, $goal, $reason): void {
            if ($profile->goal !== $goal) {
                AgentGoalChange::query()->create(['user_id' => $user->id, 'from_goal' => $profile->goal, 'to_goal' => $goal, 'reason' => $reason]);
            }

            $profile->forceFill(['goal' => $goal, 'welcomed_at' => $profile->welcomed_at ?? now(), 'snoozed_goal' => null, 'snoozed_until' => null])->save();
        });

        return $this->show($user);
    }

    /**
     * @param  list<string>  $flags
     * @return ModeState
     */
    public function setFlags(User $user, array $flags): array
    {
        $this->allow($user);
        $this->profile($user)->forceFill(['flags' => array_values(array_intersect(self::FLAGS, $flags))])->save();

        return $this->show($user);
    }

    /**
     * "Not yet": the suggestion waits 30 days.
     *
     * @return ModeState
     */
    public function snooze(User $user): array
    {
        $this->allow($user);
        $profile = $this->profile($user);
        $suggestion = $this->suggestion($user, $profile, $this->licence($user));

        if ($suggestion !== null) {
            $profile->forceFill(['snoozed_goal' => $suggestion['goal'], 'snoozed_until' => now()->addDays(self::SNOOZE_DAYS)])->save();
        }

        return $this->show($user);
    }

    /** The agent's goal, or Launch until they pick one. */
    public function goalOf(User $user): string
    {
        $goal = AgentProfile::query()->where('user_id', $user->id)->value('goal');

        return is_string($goal) ? $goal : 'launch';
    }

    /** @return list<string> */
    public function flagsOf(User $user): array
    {
        return AgentProfile::query()->where('user_id', $user->id)->first()->flags ?? [];
    }

    /** Verified closings: deals closed in a transaction tool (not ones typed into REOS). */
    public function verifiedClosings(User $user, ?CarbonImmutable $since = null): int
    {
        return Deal::query()->where('user_id', $user->id)->where('stage', 'closed')->where('source', '!=', 'reos')
            ->when($since !== null, static fn ($q) => $q->where('closed_at', '>=', $since))->count();
    }

    private function profile(User $user): AgentProfile
    {
        return AgentProfile::query()->firstOrCreate(['user_id' => $user->id]);
    }

    /** The agent's main licence: the oldest active one, else the newest. */
    private function licence(User $user): ?Licence
    {
        return Licence::query()->where('user_id', $user->id)->whereIn('status', [LicenceStatus::Active, LicenceStatus::Inactive])
            ->orderByRaw('licensed_on is null')->orderBy('licensed_on')->first()
            ?? Licence::query()->where('user_id', $user->id)->latest('id')->first();
    }

    /** @return array{unlocked: bool, unlocks_on: string|null, years: int} */
    private function own(User $user, ?Licence $licence): array
    {
        $years = $this->number($licence->state_code ?? 'TX', 'agents.own_years', 4);

        if (Licence::query()->where('user_id', $user->id)->where('type', LicenceType::Broker)->where('status', LicenceStatus::Active)->exists()) {
            return ['unlocked' => true, 'unlocks_on' => null, 'years' => $years];
        }

        $since = $licence?->licensed_on;
        $unlocks = $since === null ? null : CarbonImmutable::parse($since)->addYears($years);

        return ['unlocked' => $unlocks !== null && $unlocks->lte(now()), 'unlocks_on' => $unlocks?->toDateString(), 'years' => $years];
    }

    /** Launch for agents licensed under a year with no closings; Grow otherwise. */
    private function defaultGoal(User $user, ?Licence $licence): string
    {
        $since = $licence?->licensed_on;
        $new = $since === null || CarbonImmutable::parse($since)->gt(now()->subYear());

        return $new && $this->verifiedClosings($user) === 0 ? 'launch' : 'grow';
    }

    /** @return array{goal: string, closings: int}|null */
    private function suggestion(User $user, AgentProfile $profile, ?Licence $licence): ?array
    {
        $state = $licence->state_code ?? 'TX';
        $suggestion = null;

        if ($profile->goal === 'launch') {
            $closings = $this->verifiedClosings($user);
            $suggestion = $closings >= $this->number($state, 'agents.grow_closings', 1) ? ['goal' => 'grow', 'closings' => $closings] : null;
        }

        if (in_array($profile->goal, ['launch', 'grow'], true)) {
            $year = $this->verifiedClosings($user, CarbonImmutable::now()->subYear());
            $suggestion = $year >= $this->number($state, 'agents.lead_closings', 12) ? ['goal' => 'lead', 'closings' => $year] : $suggestion;
        }

        $snoozed = $suggestion !== null && $profile->snoozed_goal === $suggestion['goal'] && $profile->snoozed_until !== null && $profile->snoozed_until->isFuture();

        return $snoozed ? null : $suggestion;
    }

    private function number(string $state, string $key, int $default): int
    {
        $value = $this->rules->inForce($state, CarbonImmutable::now())[$key]->value['number'] ?? null;

        return is_int($value) ? $value : $default;
    }

    private function allow(User $user): void
    {
        abort_unless($this->roles->for($user)?->role === Role::Agent, 404);
    }
}
