<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Organisation\Concerns\ForAdminOrganisation;
use App\Http\Requests\Organisation\SendInvitesRequest;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Tenancy\Invitations;
use App\Support\Plural;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Send, resend and revoke invitations; ask for more seats (A4.3a, screens 32 and 41). */
final class InvitationController extends Controller
{
    use ForAdminOrganisation;

    public function store(SendInvitesRequest $request, #[CurrentUser] User $user, Invitations $invitations): RedirectResponse
    {
        $organisation = $this->adminOrganisation($user);
        $team = $request->filled('team_id') ? Organisation::query()->find($request->integer('team_id')) : null;
        abort_if($request->filled('team_id') && $team === null, 422);

        $parsed = $request->parsed();
        $result = $invitations->invite($organisation, $user, $parsed['people'], $request->role(), $team, $request->string('locale')->toString());
        $skipped = [...$result['skipped'], ...array_map(static fn (string $line): array => ['contact' => $line, 'reason' => 'invalid'], $parsed['invalid'])];

        Inertia::flash('toast', $result['sent'] > 0
            ? ['type' => 'success', 'message' => Plural::of('invites.sent', $result['sent'])]
            : ['type' => 'info', 'message' => __('invites.noneSent')]);
        Inertia::flash('inviteResult', ['sent' => $result['sent'], 'skipped' => $skipped]);

        return back();
    }

    public function resend(int $invitation, #[CurrentUser] User $user, Invitations $invitations): RedirectResponse
    {
        $found = $invitations->manageable($this->adminOrganisation($user), $invitation);
        abort_if($found === null, 404);

        $invitations->resend($found, $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('invites.resent', ['contact' => $found->email ?? $found->name ?? ''])]);

        return back();
    }

    public function destroy(int $invitation, #[CurrentUser] User $user, Invitations $invitations): RedirectResponse
    {
        $found = $invitations->manageable($this->adminOrganisation($user), $invitation);
        abort_if($found === null, 404);

        $invitations->revoke($found, $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('invites.revoked')]);

        return back();
    }

    public function requestSeats(Request $request, #[CurrentUser] User $user, Invitations $invitations): RedirectResponse
    {
        $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $asked = $invitations->askForSeats($this->adminOrganisation($user), $user, $request->filled('note') ? $request->string('note')->toString() : null);
        Inertia::flash('toast', $asked
            ? ['type' => 'success', 'message' => __('invites.seatsAsked')]
            : ['type' => 'info', 'message' => __('invites.seatsAlreadyAsked')]);

        return back();
    }
}
