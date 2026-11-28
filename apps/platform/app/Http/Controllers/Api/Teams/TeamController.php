<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Teams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\AgreementRequest;
use App\Http\Requests\Teams\RotationMemberRequest;
use App\Http\Requests\Teams\RoutingRulesRequest;
use App\Http\Requests\Teams\SplitQuestionRequest;
use App\Http\Requests\Teams\SplitSheetRequest;
use App\Http\Requests\Teams\TeamActionRequest;
use App\Http\Requests\Teams\TeamLeadRequest;
use App\Models\User;
use App\Teams\LeadRouting;
use App\Teams\SplitSheets;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Teams (A17.3, ADR 0084): lead routing with claim timers and fallback,
 * and split sheets from the team agreement. Team leaders run routing and
 * send sheets; agents claim leads and sign sheets.
 */
final class TeamController extends Controller
{
    /**
     * Lead routing (team leader).
     *
     * @response array{data: array{team: string, rules: array{sources: list<string>, method: 'first_to_claim'|'round_robin', claim_minutes: int, fallback: 'round_robin'|'leader', off_start: string, off_end: string}, members: list<array{id: int, name: string, active: bool, languages: list<string>, last_assigned_at: string|null}>, leads: list<array{id: string, source: string, name: string, language: string|null, status: string, how: string|null, agent: string|null, claim_by: string|null, created_at: string, auto_reply: string|null}>}}
     */
    public function routing(TeamActionRequest $request, #[CurrentUser] User $user, LeadRouting $routing): JsonResponse
    {
        return response()->json(['data' => $routing->board($user)]);
    }

    /**
     * Change the routing rules.
     *
     * @response array{data: array{team: string, rules: array{sources: list<string>, method: 'first_to_claim'|'round_robin', claim_minutes: int, fallback: 'round_robin'|'leader', off_start: string, off_end: string}, members: list<array{id: int, name: string, active: bool, languages: list<string>, last_assigned_at: string|null}>, leads: list<array{id: string, source: string, name: string, language: string|null, status: string, how: string|null, agent: string|null, claim_by: string|null, created_at: string, auto_reply: string|null}>}}
     */
    public function rules(RoutingRulesRequest $request, #[CurrentUser] User $user, LeadRouting $routing): JsonResponse
    {
        return response()->json(['data' => $routing->setRules($user, $request->validated())]);
    }

    /**
     * An agent in or out of the rotation.
     *
     * @response array{data: array{team: string, rules: array{sources: list<string>, method: 'first_to_claim'|'round_robin', claim_minutes: int, fallback: 'round_robin'|'leader', off_start: string, off_end: string}, members: list<array{id: int, name: string, active: bool, languages: list<string>, last_assigned_at: string|null}>, leads: list<array{id: string, source: string, name: string, language: string|null, status: string, how: string|null, agent: string|null, claim_by: string|null, created_at: string, auto_reply: string|null}>}}
     */
    public function member(RotationMemberRequest $request, int $agent, #[CurrentUser] User $user, LeadRouting $routing): JsonResponse
    {
        /** @var list<string> $languages */
        $languages = array_values(array_filter((array) $request->input('languages', []), 'is_string'));

        return response()->json(['data' => $routing->setMember($user, $agent, $request->boolean('active'), $languages)]);
    }

    /**
     * Add a lead for the team.
     *
     * It's routed at once: offered to claim, or assigned in turn.
     *
     * @status 201
     *
     * @response array{data: array{id: string, status: string}}
     */
    public function lead(TeamLeadRequest $request, #[CurrentUser] User $user, LeadRouting $routing): JsonResponse
    {
        $text = static fn (string $k): ?string => $request->filled($k) ? $request->string($k)->toString() : null;
        $lead = $routing->receive($routing->team($user), ['source' => $request->string('source')->toString(), 'name' => $request->string('name')->toString(), 'email' => $text('email'), 'phone' => $text('phone'), 'language' => $text('language'), 'message' => $text('message')]);

        return response()->json(['data' => ['id' => $lead->uuid, 'status' => $lead->status]], 201);
    }

    /**
     * Claim a team lead (agent).
     *
     * The first to claim in time gets it, in their CRM with a follow-up task. 422 when it's taken or the time is up.
     *
     * @response array{data: array{id: string, status: string, contact: string|null}}
     */
    public function claim(TeamActionRequest $request, string $offer, #[CurrentUser] User $user, LeadRouting $routing): JsonResponse
    {
        return response()->json(['data' => $routing->claimRow($user, $offer)]);
    }

    /**
     * My team leads and split sheets (agent).
     *
     * @response array{offered: list<array{id: string, source: string, name: string, language: string|null, status: string, how: string|null, agent: string|null, claim_by: string|null, created_at: string, auto_reply: string|null}>, splits: list<array{id: string, agent: string, property: string, closing_on: string, lead_source: string|null, gross: int, brokerage: int, team: int, agent_share: int, status: string, signed_at: string|null, exported_at: string|null}>}
     */
    public function mine(TeamActionRequest $request, #[CurrentUser] User $user, LeadRouting $routing, SplitSheets $splits): JsonResponse
    {
        return response()->json(['offered' => $routing->offeredTo($user), 'splits' => $splits->mine($user)]);
    }

    /**
     * Split sheets and the team agreement (team leader).
     *
     * @response array{data: array{team: string, agreement: array{brokerage_percent: int, team_percent: int, agent_percent: int}, sheets: list<array{id: string, agent: string, property: string, closing_on: string, lead_source: string|null, gross: int, brokerage: int, team: int, agent_share: int, status: string, signed_at: string|null, exported_at: string|null}>, agents: list<array{id: int, name: string}>}}
     */
    public function splits(#[CurrentUser] User $user, SplitSheets $splits): JsonResponse
    {
        return response()->json(['data' => $splits->forLeader($user)]);
    }

    /**
     * Change the team agreement.
     *
     * @response array{data: array{brokerage_percent: int, team_percent: int, agent_percent: int}}
     */
    public function agreement(AgreementRequest $request, #[CurrentUser] User $user, SplitSheets $splits): JsonResponse
    {
        return response()->json(['data' => $splits->setAgreement($user, $request->integer('brokerage_percent'), $request->integer('team_percent'))]);
    }

    /**
     * Send a split sheet for a closing.
     *
     * @status 201
     *
     * @response array{data: array{id: string, status: string}}
     */
    public function sendSplit(SplitSheetRequest $request, #[CurrentUser] User $user, SplitSheets $splits): JsonResponse
    {
        $sheet = $splits->create($user, $request->integer('agent_id'), $request->string('property')->toString(), $request->string('closing_on')->toString(), $request->integer('gross_cents'), $request->filled('lead_source') ? $request->string('lead_source')->toString() : null);

        return response()->json(['data' => ['id' => $sheet->uuid, 'status' => $sheet->status]], 201);
    }

    /**
     * Sign a split sheet (agent).
     *
     * @response array{data: array{id: string, status: string}}
     */
    public function sign(string $sheet, #[CurrentUser] User $user, SplitSheets $splits): JsonResponse
    {
        $signed = $splits->sign($user, $sheet);

        return response()->json(['data' => ['id' => $signed->uuid, 'status' => $signed->status]]);
    }

    /**
     * Ask about a split sheet (agent).
     *
     * Opens a chat with the team leader.
     *
     * @response array{conversation: string}
     */
    public function question(SplitQuestionRequest $request, string $sheet, #[CurrentUser] User $user, SplitSheets $splits): JsonResponse
    {
        return response()->json(['conversation' => $splits->question($user, $sheet, $request->string('text')->toString())]);
    }
}
