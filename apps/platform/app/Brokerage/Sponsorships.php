<?php

declare(strict_types=1);

namespace App\Brokerage;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Licences\Regulators\RegulatorFile;
use App\Models\Deal;
use App\Models\Licence;
use App\Models\Organisation;
use App\Models\RegulatorRecord;
use App\Models\User;
use App\Notifications\SponsorshipChanged;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Tenancy\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Sponsorship join and release (A16.2, ADR 0077). A Texas sales agent is
 * sponsored by one broker at a time (A14.3).
 *
 * - **Join** is recorded when an agent accepts the brokerage's invitation.
 * - **Release** is recorded by the designated broker (or someone with
 *   "sponsor agents"), with a date up to 30 days ahead and a **handover**:
 *   the agent's open deals go to another agent or broker of the brokerage,
 *   who is told, and so is the agent. On the date, the agent's role ends.
 * - **Confirmed** when TREC's nightly file shows the change: the agent
 *   under the brokerage's licence after a join, and no longer after a
 *   release. Until then each change shows "Waiting for TREC".
 *
 * @phpstan-type Change array{id: string, kind: 'join'|'release', agent: string, agent_id: int, effective_on: string, handover_to: string|null, deals: int, status: 'recorded'|'done'|'confirmed', confirmed_at: string|null}
 * @phpstan-type Board array{brokerage: string, trec_number: string|null, changes: list<Change>, agents: list<array{id: int, name: string, open_deals: int}>, handover: list<array{id: int, name: string}>}
 */
final readonly class Sponsorships
{
    public const MAX_DAYS_AHEAD = 30;

    public function __construct(
        private BrokerageAccess $access,
        private BrokerageStaff $staff,
        private Memberships $memberships,
        private Tenancy $tenancy,
        private AuditLogger $audit,
    ) {}

    /** @return Board */
    public function board(User $user): array
    {
        $brokerage = $this->brokerage($user);
        $agents = $this->memberships->holdersInTree(Role::Agent, $brokerage);
        $brokers = $this->memberships->holdersInTree(Role::Broker, $brokerage);
        $rows = $this->tenancy->run($brokerage->id, static fn () => DB::table('sponsorship_changes')->where('organisation_id', $brokerage->id)->orderByDesc('id')->limit(50)->get());
        $names = [];

        foreach (User::query()->whereIn('id', $rows->pluck('user_id')->merge($rows->pluck('handover_to'))->filter()->unique())->get(['id', 'name']) as $person) {
            $names[$person->id] = (string) $person->name;
        }
        $changes = [];

        foreach ($rows as $r) {
            $changes[] = [
                'id' => (string) $r->uuid, 'kind' => $r->kind === 'join' ? 'join' : 'release', 'agent' => $names[(int) $r->user_id] ?? '', 'agent_id' => (int) $r->user_id,
                'effective_on' => substr((string) $r->effective_on, 0, 10), 'handover_to' => $r->handover_to === null ? null : $names[(int) $r->handover_to] ?? '',
                'deals' => count(is_string($r->handed_over) ? (array) json_decode($r->handed_over, true) : []),
                'status' => match ((string) $r->status) {
                    'confirmed' => 'confirmed',
                    'done' => 'done',
                    default => 'recorded',
                },
                'confirmed_at' => $r->confirmed_at === null ? null : CarbonImmutable::parse((string) $r->confirmed_at)->toIso8601String(),
            ];
        }

        $open = [];

        foreach ($this->tenancy->asPlatform(static fn () => Deal::query()->whereIn('user_id', $agents->pluck('id'))->whereIn('stage', ['active', 'under_contract'])->get(['user_id'])) as $deal) {
            $open[$deal->user_id] = ($open[$deal->user_id] ?? 0) + 1;
        }

        return [
            'brokerage' => $brokerage->name, 'trec_number' => $brokerage->trec_number, 'changes' => $changes,
            'agents' => array_values($agents->sortBy('name')->map(static fn (User $a): array => ['id' => $a->id, 'name' => (string) $a->name, 'open_deals' => $open[$a->id] ?? 0])->all()),
            'handover' => array_values($agents->merge($brokers)->unique('id')->sortBy('name')->map(static fn (User $u): array => ['id' => $u->id, 'name' => (string) $u->name])->all()),
        ];
    }

    /**
     * Releases an agent on a date, handing their open deals over.
     *
     * @return Board
     */
    public function release(User $user, int $agentId, string $effectiveOn, ?int $handoverTo): array
    {
        $brokerage = $this->brokerage($user);
        $agent = $this->memberships->holdersInTree(Role::Agent, $brokerage)->firstWhere('id', $agentId) ?? throw new NotFoundHttpException;
        $date = CarbonImmutable::parse($effectiveOn)->startOfDay();

        if ($date->lt(CarbonImmutable::today()) || $date->gt(CarbonImmutable::today()->addDays(self::MAX_DAYS_AHEAD))) {
            throw ValidationException::withMessages(['effective_on' => __('brokerage.sponsorship.errors.date', ['days' => self::MAX_DAYS_AHEAD])]);
        }

        $deals = $this->tenancy->asPlatform(static fn () => Deal::query()->where('user_id', $agent->id)->whereIn('stage', ['active', 'under_contract'])->get(['id', 'uuid', 'property', 'organisation_id']));
        $receiver = null;

        if ($deals->isNotEmpty()) {
            $people = $this->memberships->holdersInTree(Role::Agent, $brokerage)->merge($this->memberships->holdersInTree(Role::Broker, $brokerage));
            $receiver = $handoverTo === null || $handoverTo === $agent->id ? null : $people->firstWhere('id', $handoverTo);

            if ($receiver === null) {
                throw ValidationException::withMessages(['handover_to' => __('brokerage.sponsorship.errors.handover', ['count' => $deals->count()])]);
            }
        }

        if ($this->tenancy->run($brokerage->id, static fn (): bool => DB::table('sponsorship_changes')->where('organisation_id', $brokerage->id)->where('user_id', $agent->id)->where('kind', 'release')->where('status', 'recorded')->exists())) {
            throw ValidationException::withMessages(['agent' => __('brokerage.sponsorship.errors.already')]);
        }

        DB::transaction(function () use ($brokerage, $agent, $deals, $receiver, $date, $user): void {
            // The handover: the deals stay with the brokerage, now with the receiver.
            if ($receiver !== null) {
                $this->tenancy->asPlatform(static fn () => Deal::query()->whereIn('id', $deals->pluck('id'))->update(['user_id' => $receiver->id]));
            }

            $this->tenancy->run($brokerage->id, static fn () => DB::table('sponsorship_changes')->insert([
                'uuid' => (string) Str::uuid7(), 'organisation_id' => $brokerage->id, 'user_id' => $agent->id, 'kind' => 'release', 'effective_on' => $date->toDateString(),
                'handover_to' => $receiver?->id, 'handed_over' => json_encode($deals->map(static fn (Deal $d): array => ['uuid' => $d->uuid, 'property' => $d->property])->values()->all(), JSON_THROW_ON_ERROR),
                'status' => 'recorded', 'recorded_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
            ]));
        });

        $agent->notify(new SponsorshipChanged('released', $brokerage->name, (string) $agent->name, $date->toDateString(), $deals->count()));
        $receiver?->notify(new SponsorshipChanged('handover', $brokerage->name, (string) $agent->name, $date->toDateString(), $deals->count()));
        $this->audit->record('sponsorship.released', 'user', (string) $agent->id, ['effective_on' => $date->toDateString(), 'handover_to' => $receiver?->id, 'deals' => $deals->pluck('uuid')->all()], $user, $brokerage->id);

        if ($date->isToday()) {
            $this->endRole($brokerage, $agent, $user);
        }

        return $this->board($user);
    }

    /** An agent accepted the brokerage's (or a team's) invitation: the join, to confirm with TREC. */
    public function joined(User $agent, Organisation $org): void
    {
        $brokerage = $org->type === OrganisationType::Team && $org->parent !== null ? $org->parent : $org;

        if ($brokerage->type !== OrganisationType::Brokerage) {
            return;
        }

        $this->tenancy->run($brokerage->id, static fn () => DB::table('sponsorship_changes')->insert([
            'uuid' => (string) Str::uuid7(), 'organisation_id' => $brokerage->id, 'user_id' => $agent->id, 'kind' => 'join', 'effective_on' => now()->toDateString(),
            'status' => 'recorded', 'recorded_by' => $agent->id, 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    /**
     * Daily (after TREC's import): ends the roles of releases that are due,
     * and confirms changes TREC's file now shows. Returns [ended, confirmed].
     *
     * @return array{0: int, 1: int}
     */
    public function run(): array
    {
        $ended = 0;
        $confirmed = 0;

        foreach (Organisation::query()->where('type', OrganisationType::Brokerage)->get() as $brokerage) {
            $rows = $this->tenancy->run($brokerage->id, static fn () => DB::table('sponsorship_changes')->where('organisation_id', $brokerage->id)->whereIn('status', ['recorded', 'done'])->get());

            foreach ($rows as $row) {
                $agent = User::query()->whereKey((int) $row->user_id)->first();

                if (! $agent instanceof User) {
                    continue;
                }

                if ($row->kind === 'release' && $row->status === 'recorded' && CarbonImmutable::parse((string) $row->effective_on)->lte(CarbonImmutable::today())) {
                    $this->endRole($brokerage, $agent, null);
                    $this->tenancy->run($brokerage->id, static fn () => DB::table('sponsorship_changes')->where('id', $row->id)->update(['status' => 'done', 'updated_at' => now()]));
                    $row->status = 'done';
                    $ended++;
                }

                if (($row->kind === 'join' || $row->status === 'done') && $this->trecShows($brokerage, $agent, $row->kind === 'join')) {
                    $this->tenancy->run($brokerage->id, static fn () => DB::table('sponsorship_changes')->where('id', $row->id)->update(['status' => 'confirmed', 'confirmed_at' => now(), 'updated_at' => now()]));
                    $this->audit->record('sponsorship.confirmed', 'user', (string) $agent->id, ['kind' => $row->kind], null, $brokerage->id);
                    $confirmed++;
                }
            }
        }

        return [$ended, $confirmed];
    }

    /** TREC's file shows the agent under the brokerage's licence (or no longer, for a release). */
    private function trecShows(Organisation $brokerage, User $agent, bool $sponsored): bool
    {
        if ($brokerage->trec_number === null) {
            return false;
        }

        $number = Licence::query()->where('user_id', $agent->id)->where('state_code', 'TX')->value('number');

        if (! is_string($number)) {
            return false;
        }

        $digits = RegulatorFile::digits($number);
        $record = $digits === '' ? null : RegulatorRecord::query()->where('authority', 'TREC')->where('kind', 'agents')->where('number_digits', $digits)->first();

        if ($record === null) {
            return false;
        }

        $sponsor = RegulatorFile::digits($record->sponsor_number);
        $under = $sponsor !== '' && $sponsor === RegulatorFile::digits($brokerage->trec_number);

        return $sponsored ? $under : ! $under;
    }

    private function endRole(Organisation $brokerage, User $agent, ?User $actor): void
    {
        foreach ($this->memberships->of($agent) as $m) {
            if ($m->role === Role::Agent && ($m->organisation->id === $brokerage->id || $m->organisation->parent_id === $brokerage->id)) {
                $this->tenancy->run($m->organisation->id, fn () => $this->memberships->revoke($agent, Role::Agent, $m->organisation, $actor));
            }
        }
    }

    private function brokerage(User $user): Organisation
    {
        [$brokerage, $team] = $this->access->scope($user);

        if ($team !== null) {
            throw new NotFoundHttpException;
        }

        $this->staff->allow($user, $brokerage, 'sponsor_agents');

        return $brokerage;
    }
}
