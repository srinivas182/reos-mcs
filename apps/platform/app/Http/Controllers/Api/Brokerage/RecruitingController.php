<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Brokerage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Brokerage\InviteAgentRequest;
use App\Http\Requests\Brokerage\SponsorCapRequest;
use App\Licences\Journey\BrokerOffers;
use App\Models\User;
use App\Recruiting\OpenAgents;
use App\Recruiting\SponsoredHelp;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Recruiting on the phone (A17.2, ADR 0083; broker screens 26 and 27):
 * sponsored AI help for candidates, and licensed agents open to a move.
 */
final class RecruitingController extends Controller
{
    /**
     * Licensed agents open to a move.
     *
     * Agents who turned on "Open to recruiting", licensed in the brokerage's states and not with it: first name and initial, closings in 12 months, focus and why they match. Their broker is never told.
     *
     * @response array{data: list<array{id: int, name: string, closings: int, focus: string, reasons: list<'closings'|'city'|'new'>, invited_at: string|null, conversation: string|null}>}
     */
    public function agents(#[CurrentUser] User $user, BrokerOffers $offers, OpenAgents $agents): JsonResponse
    {
        return response()->json(['data' => $agents->list($offers->brokerageOf($user))]);
    }

    /**
     * Invite an agent to talk.
     *
     * Opens a private chat in Messages (once every 90 days).
     *
     * @response array{conversation: string}
     */
    public function invite(InviteAgentRequest $request, int $agent, #[CurrentUser] User $user, BrokerOffers $offers, OpenAgents $agents): JsonResponse
    {
        return response()->json(['conversation' => $agents->invite($offers->brokerageOf($user), $user, $agent, $request->string('message')->toString())]);
    }

    /**
     * Sponsored AI help.
     *
     * The brokerage's credit pool and each sponsored candidate's cap, use and status.
     *
     * @response array{data: array{pool: int, candidates: list<array{id: string, candidate: string, cap: int, used: int, status: 'active'|'used_up'|'ended', end_reason: string|null, since: string}>}}
     */
    public function sponsorships(#[CurrentUser] User $user, BrokerOffers $offers, SponsoredHelp $help): JsonResponse
    {
        return response()->json(['data' => $help->board($offers->brokerageOf($user))]);
    }

    /**
     * Change a candidate's cap.
     *
     * @response array{data: array{pool: int, candidates: list<array{id: string, candidate: string, cap: int, used: int, status: 'active'|'used_up'|'ended', end_reason: string|null, since: string}>}}
     */
    public function cap(SponsorCapRequest $request, string $sponsorship, #[CurrentUser] User $user, BrokerOffers $offers, SponsoredHelp $help): JsonResponse
    {
        return response()->json(['data' => $help->setCap($offers->brokerageOf($user), $user, $sponsorship, $request->integer('cap'))]);
    }
}
