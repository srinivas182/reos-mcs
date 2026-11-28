<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Agents;

use App\Agents\BrokerageMoves;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agents\MoveRequest;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\Tenancy\Invitations;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/** Joining another brokerage or team from the phone (A14.3, ADR 0063): with an invite code. */
final class MoveController extends Controller
{
    /**
     * Check an invite code.
     *
     * Who invites me and as what, and — when I'm an agent at another brokerage or team — what moves with me, what stays with the old brokerage and what ends. 422 when the code is unknown, closed or for someone else.
     *
     * @response array{data: array{tenant: string, team: string|null, role: string, move: array{kind: 'brokerage'|'team', from: array{id: int, name: string}, to: array{id: int, name: string}, moves: list<array{key: 'licenses'|'ce_hours'|'certificates'|'contacts'|'posts'|'points'|'practice', count: int}>, stays: list<array{key: 'deals'|'compliance', count: int}>, ends: list<array{key: 'mentor'|'pods'|'approvals', count: int}>}|null}}
     */
    public function preview(MoveRequest $request, #[CurrentUser] User $user, Invitations $invitations, BrokerageMoves $moves): JsonResponse
    {
        $invitation = $this->invitation($request, $user, $invitations);

        return response()->json(['data' => [
            'tenant' => $invitation->organisation->name,
            'team' => $invitation->team?->name,
            'role' => $invitation->role->value,
            'move' => $moves->preview($user, $invitation),
        ]]);
    }

    /**
     * Join.
     *
     * Accepts the invitation and switches to the new role. An agent elsewhere must send `confirm: true` (422 `move` otherwise): they leave the old brokerage or team, their own data stays with them, and the old brokerage keeps its records.
     *
     * @response array{data: array{organisation: string, role: string}}
     */
    public function accept(MoveRequest $request, #[CurrentUser] User $user, Invitations $invitations, BrokerageMoves $moves, ActiveRole $roles): JsonResponse
    {
        $invitation = $this->invitation($request, $user, $invitations);
        $moves->accept($user, $invitation, $request->boolean('confirm'));
        $roles->choose($user, $invitation->role, $invitation->target()->id);

        return response()->json(['data' => ['organisation' => $invitation->target()->name, 'role' => $invitation->role->value]]);
    }

    private function invitation(MoveRequest $request, User $user, Invitations $invitations): Invitation
    {
        $invitation = $invitations->findByCode($request->string('code')->toString());

        if ($invitation === null || $invitation->state() !== Invitation::PENDING) {
            throw ValidationException::withMessages(['code' => __('invites.errors.code')]);
        }

        if (! $invitations->isFor($invitation, $user)) {
            throw ValidationException::withMessages(['code' => __('invites.errors.otherPerson')]);
        }

        return $invitation;
    }
}
