<?php

declare(strict_types=1);

namespace App\Brokerage;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * People and permissions (A15.2, broker journey screen 34 "people and
 * permissions", ADR 0071): who runs the brokerage and what each may do.
 *
 * - **Designated broker:** everything, and the only one who changes roles,
 *   duties, seats and who pays. The brokerage's creator (A15.1a), or its
 *   first broker for brokerages from before.
 * - **Office admin:** the broker role at the brokerage; each duty is off
 *   until the designated broker turns it on.
 * - **Team leader:** the broker role on one of its teams; duties apply to
 *   their team only (A15.3 scopes approvals and questions to the team).
 *
 * The duties: approve posts, answer escalated questions, manage policies
 * (the manual, brokerage rules, the advertising policy), and sponsor agents
 * (bring in and invite). Changes are audited.
 *
 * @phpstan-type StaffRow array{user_id: int, name: string, title: 'designated_broker'|'office_admin'|'team_leader', team: string|null, duties: list<string>, editable: bool}
 */
final readonly class BrokerageStaff
{
    public const DUTIES = ['approve_posts', 'answer_escalations', 'manage_policies', 'sponsor_agents'];

    public const TITLES = ['designated_broker', 'office_admin', 'team_leader'];

    public function __construct(private Memberships $memberships, private AuditLogger $audit, private Tenancy $tenancy) {}

    /** @return list<StaffRow> */
    public function list(Organisation $brokerage): array
    {
        $rows = [];
        $designated = $this->designatedId($brokerage);
        $saved = $this->saved($brokerage);

        foreach ($this->memberships->holdersIn(Role::Broker, $brokerage) as $user) {
            $rows[] = $this->row($user, $user->id === $designated ? 'designated_broker' : 'office_admin', null, $saved[$user->id] ?? []);
        }

        foreach ($brokerage->children()->where('type', OrganisationType::Team)->get() as $team) {
            foreach ($this->memberships->holdersIn(Role::Broker, $team) as $user) {
                $rows[] = $this->row($user, 'team_leader', $team->name, $saved[$user->id] ?? []);
            }
        }

        return $rows;
    }

    /**
     * The designated broker turns duties on or off for an office admin or team leader.
     *
     * @param  list<string>  $duties
     */
    public function setDuties(Organisation $brokerage, User $actor, int $userId, array $duties): void
    {
        $this->designatedOnly($brokerage, $actor);
        $row = collect($this->list($brokerage))->firstWhere('user_id', $userId);

        if ($row === null) {
            throw new NotFoundHttpException;
        }

        if ($row['title'] === 'designated_broker') {
            throw ValidationException::withMessages(['duties' => __('brokerage.staff.designatedAll')]);
        }

        $duties = array_values(array_intersect(self::DUTIES, $duties));
        $before = $row['duties'];
        $this->tenancy->run($brokerage->id, static fn () => DB::table('brokerage_staff')->updateOrInsert(
            ['organisation_id' => $brokerage->id, 'user_id' => $userId],
            ['title' => $row['title'], 'duties' => json_encode($duties), 'updated_by' => $actor->id, 'updated_at' => now(), 'created_at' => now()],
        ));
        $this->audit->record('brokerage.duties_changed', 'user', (string) $userId, ['before' => $before, 'after' => $duties], $actor, $brokerage->id);
    }

    /** Records the brokerage's creator as its designated broker (A15.1a). */
    public function makeDesignated(Organisation $brokerage, User $user): void
    {
        $this->tenancy->run($brokerage->id, static fn () => DB::table('brokerage_staff')->updateOrInsert(
            ['organisation_id' => $brokerage->id, 'user_id' => $user->id],
            ['title' => 'designated_broker', 'duties' => '[]', 'updated_at' => now(), 'created_at' => now()],
        ));
    }

    public function isDesignated(Organisation $brokerage, User $user): bool
    {
        return $this->designatedId($brokerage) === $user->id;
    }

    /** Whether someone who runs the brokerage (or one of its teams) may do this. */
    public function can(User $user, Organisation $brokerage, string $duty): bool
    {
        if ($this->isDesignated($brokerage, $user)) {
            return true;
        }

        return in_array($duty, $this->saved($brokerage)[$user->id] ?? [], true);
    }

    /** Stops anyone without the duty (a 403 with the reason). */
    public function allow(User $user, Organisation $brokerage, string $duty): void
    {
        abort_unless($this->can($user, $brokerage, $duty), 403, __('brokerage.staff.notAllowed'));
    }

    public function designatedOnly(Organisation $brokerage, User $user): void
    {
        abort_unless($this->isDesignated($brokerage, $user), 403, __('brokerage.staff.designatedOnly'));
    }

    /** The designated broker: the recorded one, else the brokerage's first broker. */
    private function designatedId(Organisation $brokerage): ?int
    {
        $recorded = $this->tenancy->run($brokerage->id, static fn (): mixed => DB::table('brokerage_staff')->where('organisation_id', $brokerage->id)->where('title', 'designated_broker')->value('user_id'));

        if (is_numeric($recorded)) {
            return (int) $recorded;
        }

        $first = $this->memberships->holdersIn(Role::Broker, $brokerage)->min('id');

        return is_numeric($first) ? (int) $first : null;
    }

    /** @return array<int, list<string>> duties saved per person */
    private function saved(Organisation $brokerage): array
    {
        $rows = $this->tenancy->run($brokerage->id, static fn () => DB::table('brokerage_staff')->where('organisation_id', $brokerage->id)->get(['user_id', 'duties']));
        $out = [];

        foreach ($rows as $row) {
            $duties = json_decode(is_string($row->duties ?? null) ? $row->duties : '[]', true);
            $out[is_numeric($row->user_id ?? null) ? (int) $row->user_id : 0] = array_values(array_filter(is_array($duties) ? $duties : [], is_string(...)));
        }

        return $out;
    }

    /**
     * @param  'designated_broker'|'office_admin'|'team_leader'  $title
     * @param  list<string>  $duties
     * @return StaffRow
     */
    private function row(User $user, string $title, ?string $team, array $duties): array
    {
        return [
            'user_id' => $user->id, 'name' => (string) $user->name, 'title' => $title, 'team' => $team,
            'duties' => $title === 'designated_broker' ? self::DUTIES : array_values(array_intersect(self::DUTIES, $duties)),
            'editable' => $title !== 'designated_broker',
        ];
    }
}
