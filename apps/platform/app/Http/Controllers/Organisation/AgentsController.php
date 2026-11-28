<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organisation;

use App\Authorization\Role;
use App\Brokerage\Roster;
use App\Enums\OrganisationType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Organisation\Concerns\ForAdminOrganisation;
use App\Models\Invitation;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Tenancy\Invitations;
use App\Services\Tenancy\Seats;
use App\Support\PhoneNumber;
use App\Tenancy\Tenancy;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Agents (broker and team leader, web journey screen 32), A4.3a: the
 * roster with team and last activity, open invitations, seats, Invite
 * agents and Export. Mode, talks, deals, CE and the "needs help" signal
 * arrive with the agent activity they read (later sprints).
 */
final class AgentsController extends Controller
{
    use ForAdminOrganisation;

    public function index(#[CurrentUser] User $user, Invitations $invitations, Seats $seats): Response
    {
        $organisation = $this->adminOrganisation($user, [OrganisationType::Brokerage, OrganisationType::Team]);
        $tenant = $seats->owner($organisation);

        return Inertia::render('organisation/agents', [
            'tenant' => ['name' => $tenant->name, 'team' => $organisation->type === OrganisationType::Team ? $organisation->name : null],
            'agents' => $this->agents($organisation, $seats)->values()->all(),
            'invitations' => $invitations->of($organisation)->map(static fn (Invitation $invitation): array => [
                'id' => $invitation->id,
                'contact' => $invitation->email ?? PhoneNumber::mask((string) $invitation->phone),
                'channel' => $invitation->email !== null ? 'email' : 'text',
                'name' => $invitation->name,
                'role' => $invitation->role->value,
                'team' => $invitation->team?->name,
                'state' => $invitation->state(),
                'sent_at' => $invitation->sent_at?->toIso8601String(),
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'reminded' => $invitation->reminded_at !== null,
                'invited_by' => $invitation->inviter?->name,
            ])->values()->all(),
            'invite' => $invitations->options($organisation),
            // Bring in your agents (A15.1b): TREC's roster, for the brokerage's own brokers.
            'trec' => $organisation->type === OrganisationType::Brokerage && $organisation->trec_number !== null ? app(Roster::class)->for($organisation) : null,
        ]);
    }

    /** The roster as CSV: names, emails, teams and last activity only. */
    public function export(#[CurrentUser] User $user, Seats $seats): StreamedResponse
    {
        $organisation = $this->adminOrganisation($user, [OrganisationType::Brokerage, OrganisationType::Team]);
        $agents = $this->agents($organisation, $seats);

        return response()->streamDownload(static function () use ($agents): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, [__('agents.columns.agent'), __('agents.columns.email'), __('agents.columns.team'), __('agents.columns.lastActive')], escape: '');

            foreach ($agents as $agent) {
                // Cells starting with = + - @ are prefixed so spreadsheets never run them.
                fputcsv($out, array_map(static fn (?string $cell): string => preg_match('/^[=+\-@\t\r]/', (string) $cell) === 1 ? "'".$cell : (string) $cell, [
                    $agent['name'], $agent['email'], $agent['team'], $agent['last_active'],
                ]), escape: '');
            }

            fclose($out);
        }, 'agents-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return Collection<int, array{id: int, name: string, email: string|null, team: string|null, last_active: string|null}> */
    private function agents(Organisation $organisation, Seats $seats): Collection
    {
        $tenant = $seats->owner($organisation);
        $ids = $organisation->type === OrganisationType::Team ? [$organisation->id] : $seats->subtree($tenant);
        $rows = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', Role::Agent->value)
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->whereIn('model_has_roles.organisation_id', $ids)
            ->get(['model_has_roles.model_id', 'model_has_roles.organisation_id']);
        $teams = Organisation::query()->whereIn('id', $rows->pluck('organisation_id')->unique()->all())->where('type', OrganisationType::Team->value)->pluck('name', 'id');
        $userIds = $rows->pluck('model_id')->unique()->values()->all();
        $lastSeen = app(Tenancy::class)->asPlatform(static fn (): Collection => DB::table('known_devices')->whereIn('user_id', $userIds)
            ->groupBy('user_id')->selectRaw('user_id, max(last_seen_at) as seen')->pluck('seen', 'user_id'));

        return User::query()->whereIn('id', $userIds)->orderBy('name')->limit(2000)->get()->map(static function (User $agent) use ($rows, $teams, $lastSeen): array {
            $team = $rows->where('model_id', $agent->id)->pluck('organisation_id')->map(static fn (mixed $id): mixed => $teams[$id] ?? null)->filter()->first();
            $seen = $lastSeen[$agent->id] ?? null;

            return [
                'id' => $agent->id,
                'name' => (string) ($agent->name ?? $agent->email ?? ''),
                'email' => $agent->email,
                'team' => is_string($team) ? $team : null,
                'last_active' => is_string($seen) ? date(DATE_ATOM, (int) strtotime($seen)) : null,
            ];
        });
    }
}
