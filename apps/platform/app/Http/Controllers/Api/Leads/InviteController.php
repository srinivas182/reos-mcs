<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Leads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\InviteCodeRequest;
use App\Leads\Invites;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** Invite and earn (B14.3b, ADR 0067): points when someone you invited gets licensed. */
final class InviteController extends Controller
{
    /**
     * My invite link.
     *
     * The link to share, the points for each person who gets licensed, and the people who joined through it.
     *
     * @response array{data: array{code: string, link: string, points_each: int, earned: int, invited: list<array{name: string, joined_at: string, licensed: bool}>, invited_by: string|null, can_claim: bool}}
     */
    public function show(#[CurrentUser] User $user, Invites $invites): JsonResponse
    {
        return response()->json(['data' => $invites->summary($user)]);
    }

    /**
     * Enter who invited me.
     *
     * For someone who signed up in the app: the code from the invite link, within 30 days of signing up and before getting licensed.
     *
     * @response array{data: array{code: string, link: string, points_each: int, earned: int, invited: list<array{name: string, joined_at: string, licensed: bool}>, invited_by: string|null, can_claim: bool}}
     */
    public function claim(InviteCodeRequest $request, #[CurrentUser] User $user, Invites $invites): JsonResponse
    {
        $invites->claim($user, $request->string('code')->toString(), typed: true);

        return response()->json(['data' => $invites->summary($user)]);
    }
}
