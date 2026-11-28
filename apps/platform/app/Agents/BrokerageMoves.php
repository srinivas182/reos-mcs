<?php

declare(strict_types=1);

namespace App\Agents;

use App\Authorization\Membership;
use App\Authorization\Role;
use App\Billing\Ledger\Points;
use App\Enums\OrganisationType;
use App\Models\AgentPage;
use App\Models\CeCompletion;
use App\Models\ComplianceEvent;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Invitation;
use App\Models\Licence;
use App\Models\MentorAssignment;
use App\Models\Organisation;
use App\Models\PodMember;
use App\Models\RolePlay;
use App\Models\SocialPost;
use App\Models\User;
use App\Models\VideoPractice;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Services\Tenancy\Invitations;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Switching brokerage or team (A14.3, ADR 0063).
 *
 * A Texas sales agent is sponsored by one broker at a time, so accepting
 * an agent invitation from another brokerage **moves** the agent rather
 * than adding a second agent role. Before joining, the agent sees what
 * moves with them, what stays with the old brokerage and what ends.
 *
 * - **Moves (it's the agent's own):** licenses, CE completions and
 *   certificates, the agent's own contacts, their posts, page, points and
 *   practice. These belong to the person, not to an organisation, so
 *   nothing is copied; they simply stay with the account.
 * - **Stays with the old brokerage (its records):** deals and transaction
 *   files, the compliance log, and the brokerage's own contacts.
 * - **Ends:** the old brokerage's mentor assignment, pods, and anything
 *   waiting for the old broker's approval (posts go back to drafts, the
 *   page's request is withdrawn).
 *
 * A team move inside the same brokerage only changes the team: nothing
 * ends but the old team's role.
 *
 * @phpstan-type Count array{key: string, count: int}
 * @phpstan-type Move array{kind: 'brokerage'|'team', from: array{id: int, name: string}, to: array{id: int, name: string}, moves: list<Count>, stays: list<Count>, ends: list<Count>}
 */
final readonly class BrokerageMoves
{
    public function __construct(private Memberships $memberships, private Tenancy $tenancy, private AuditLogger $audit, private Points $points, private Invitations $invitations) {}

    /**
     * Accepts the invitation; an agent elsewhere must have confirmed the
     * move, and joining and leaving happen together or not at all.
     */
    public function accept(User $user, Invitation $invitation, bool $confirmed): void
    {
        // An invitation for someone else is refused as such by Invitations::accept.
        $from = $this->invitations->isFor($invitation, $user) ? $this->from($user, $invitation) : null;

        if ($from !== null && ! $confirmed) {
            throw ValidationException::withMessages(['move' => __('moves.confirm')]);
        }

        DB::transaction(function () use ($user, $invitation, $from): void {
            $this->invitations->accept($invitation, $user);

            if ($from !== null) {
                $this->complete($user, $from, $invitation->target());
            }
        });
    }

    /** The agent role this invitation would move the person away from, if any. */
    public function from(User $user, Invitation $invitation): ?Membership
    {
        if ($invitation->role !== Role::Agent) {
            return null;
        }

        $target = $invitation->target();

        foreach ($this->memberships->of($user) as $membership) {
            if ($membership->role === Role::Agent && $membership->organisation->id !== $target->id) {
                return $membership;
            }
        }

        return null;
    }

    /**
     * What moving to the invitation's brokerage or team would do.
     *
     * @return Move|null
     */
    public function preview(User $user, Invitation $invitation): ?array
    {
        $from = $this->from($user, $invitation);

        if ($from === null) {
            return null;
        }

        $old = $from->organisation;
        $target = $invitation->target();
        $team = self::brokerageOf($old)->id === self::brokerageOf($target)->id;
        $ce = (float) CeCompletion::query()->where('user_id', $user->id)->sum('hours');
        $counts = $this->tenancy->run($old->id, fn (): array => [
            'deals' => Deal::query()->where('user_id', $user->id)->where('organisation_id', $old->id)->count(),
            'compliance' => ComplianceEvent::query()->where('user_id', $user->id)->where('organisation_id', $old->id)->count(),
            'mentor' => MentorAssignment::query()->where('agent_id', $user->id)->where('organisation_id', $old->id)->whereNull('ended_at')->count(),
            'pods' => PodMember::query()->where('user_id', $user->id)->whereNull('left_at')->whereHas('pod', static fn ($q) => $q->where('organisation_id', $old->id))->count(),
        ]);
        $approvals = SocialPost::query()->where('user_id', $user->id)->where('status', 'approval')->count()
            + AgentPage::query()->where('user_id', $user->id)->whereNotNull('approval_requested_at')->count();

        $keep = static fn (array $rows): array => array_values(array_filter($rows, static fn (array $r): bool => $r['count'] > 0));

        return [
            'kind' => $team ? 'team' : 'brokerage',
            'from' => ['id' => $old->id, 'name' => $old->name],
            'to' => ['id' => $target->id, 'name' => $target->name],
            'moves' => $keep([
                ['key' => 'licenses', 'count' => Licence::query()->where('user_id', $user->id)->count()],
                ['key' => 'ce_hours', 'count' => (int) round($ce)],
                ['key' => 'certificates', 'count' => CeCompletion::query()->where('user_id', $user->id)->count()],
                ['key' => 'contacts', 'count' => Contact::query()->where('owner_type', 'user')->where('owner_id', $user->id)->count()],
                ['key' => 'posts', 'count' => SocialPost::query()->where('user_id', $user->id)->count()],
                ['key' => 'points', 'count' => $this->points->balance($user)],
                ['key' => 'practice', 'count' => RolePlay::query()->where('user_id', $user->id)->count() + VideoPractice::query()->where('user_id', $user->id)->count()],
            ]),
            'stays' => $team ? [] : $keep([
                ['key' => 'deals', 'count' => $counts['deals']],
                ['key' => 'compliance', 'count' => $counts['compliance']],
            ]),
            'ends' => $team ? [] : $keep([
                ['key' => 'mentor', 'count' => $counts['mentor']],
                ['key' => 'pods', 'count' => $counts['pods']],
                ['key' => 'approvals', 'count' => $approvals],
            ]),
        ];
    }

    /**
     * After joining: leaves the old brokerage or team. The agent's own data
     * stays with the account; the old brokerage keeps its records.
     */
    public function complete(User $user, Membership $from, Organisation $to): void
    {
        $old = $from->organisation;
        $team = self::brokerageOf($old)->id === self::brokerageOf($to)->id;

        $this->tenancy->run($old->id, function () use ($user, $old, $team): void {
            $this->memberships->revoke($user, Role::Agent, $old, $user);

            if (! $team) {
                MentorAssignment::query()->where('agent_id', $user->id)->where('organisation_id', $old->id)->whereNull('ended_at')->update(['ended_at' => now()]);
                PodMember::query()->where('user_id', $user->id)->whereNull('left_at')->whereHas('pod', static fn ($q) => $q->where('organisation_id', $old->id))->update(['left_at' => now()]);
            }
        });

        if (! $team) {
            // Waiting for the old broker: back to drafts; the new broker's rules apply from now on.
            SocialPost::query()->where('user_id', $user->id)->where('status', 'approval')->update(['status' => 'draft', 'approval_requested_at' => null]);
            AgentPage::query()->where('user_id', $user->id)->whereNotNull('approval_requested_at')->update(['approval_requested_at' => null, 'approval_note' => null]);
        }

        foreach ([$old->id, $to->id] as $tenant) {
            $this->tenancy->run($tenant, fn () => $this->audit->record('agent.moved', 'user', (string) $user->id, ['kind' => $team ? 'team' : 'brokerage', 'from' => $old->id, 'to' => $to->id], $user, $tenant));
        }
    }

    /** A team's brokerage, or the organisation itself. */
    private static function brokerageOf(Organisation $organisation): Organisation
    {
        if ($organisation->type === OrganisationType::Team && $organisation->parent_id !== null) {
            return Organisation::query()->find($organisation->parent_id) ?? $organisation;
        }

        return $organisation;
    }
}
