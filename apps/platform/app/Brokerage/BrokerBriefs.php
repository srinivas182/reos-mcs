<?php

declare(strict_types=1);

namespace App\Brokerage;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\BrokerBriefReady;
use App\Services\Authorization\ActiveRole;
use App\Services\Authorization\Memberships;
use Carbon\CarbonImmutable;

/**
 * The broker's morning brief (A16.3, ADR 0078): on weekday mornings, the
 * few things that need the broker today, each linking to its screen.
 *
 * - Agents who need help (top three, with why), and deals at risk.
 * - Approvals waiting, and how many are past the brokerage's limit.
 * - Agents due for CE (for those who look after sponsorship).
 * - Closings this month so far.
 *
 * Counts and stages only, like oversight: the brief never shows a client.
 *
 * @phpstan-type Brief array{date: string, brokerage: string, scope: 'brokerage'|'team', team: string|null, settings: array{quiet_days: int, no_deal_days: int, new_days: int}, need_help: array{count: int, agents: list<array{id: int, name: string, signals: list<string>}>}, at_risk: array{count: int, deals: list<array{id: string, agent: string, stage: string, city: string|null, reasons: list<string>}>}, approvals: array{waiting: int, overdue: int}, ce_due: int|null, closings: array{count: int, volume: int}}
 */
final readonly class BrokerBriefs
{
    public const TOP = 3;

    public function __construct(
        private Oversight $oversight,
        private ApprovalsQueue $approvals,
        private CeCompliance $ce,
        private Reports $reports,
        private BrokerageAccess $access,
        private BrokerageStaff $staff,
        private Memberships $memberships,
        private ActiveRole $roles,
    ) {}

    /** @return Brief */
    public function for(User $user, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        [$brokerage] = $this->access->scope($user);
        $board = $this->oversight->board($user, $now);
        $queue = $this->approvals->for($user);
        $needHelp = array_values(array_filter($board['agents'], static fn (array $a): bool => array_diff($a['signals'], ['new']) !== []));

        return [
            'date' => $now->toDateString(), 'brokerage' => $board['brokerage'], 'scope' => $board['scope'], 'team' => $board['team'], 'settings' => $board['settings'],
            'need_help' => [
                'count' => count($needHelp),
                'agents' => array_map(static fn (array $a): array => ['id' => $a['id'], 'name' => $a['name'], 'signals' => $a['signals']], array_slice($needHelp, 0, self::TOP)),
            ],
            'at_risk' => [
                'count' => count($board['at_risk']),
                'deals' => array_map(static fn (array $d): array => ['id' => $d['id'], 'agent' => $d['agent'], 'stage' => $d['stage'], 'city' => $d['city'], 'reasons' => $d['reasons']], array_slice($board['at_risk'], 0, self::TOP)),
            ],
            'approvals' => ['waiting' => count($queue['items']), 'overdue' => $queue['counts']['overdue']],
            'ce_due' => $this->staff->can($user, $brokerage, 'sponsor_agents') ? $this->ce->table($user, $now->startOfDay())['counts']['due'] : null,
            'closings' => $this->reports->monthToDate($user, $now),
        ];
    }

    /** Whether the person's active role gets the broker's brief (a broker in a brokerage or a team). */
    public function shows(User $user): bool
    {
        $m = $this->roles->for($user);

        return $m !== null && $m->role === Role::Broker && in_array($m->organisation->type, [OrganisationType::Brokerage, OrganisationType::Team], true);
    }

    /**
     * Weekday mornings (7:30 Chicago): tells each broker their brief is
     * ready, when there's something in it. Returns how many were told.
     */
    public function sendDue(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        if ($now->setTimezone('America/Chicago')->isWeekend()) {
            return 0;
        }

        $sent = 0;

        foreach (Organisation::query()->where('type', OrganisationType::Brokerage)->get() as $brokerage) {
            foreach ($this->memberships->holdersInTree(Role::Broker, $brokerage) as $broker) {
                if (! $this->shows($broker)) {
                    continue;
                }

                $brief = $this->for($broker, $now);

                if ($brief['need_help']['count'] + $brief['at_risk']['count'] + $brief['approvals']['waiting'] + ($brief['ce_due'] ?? 0) === 0) {
                    continue;
                }

                $broker->notify(new BrokerBriefReady);
                $sent++;
            }
        }

        return $sent;
    }
}
