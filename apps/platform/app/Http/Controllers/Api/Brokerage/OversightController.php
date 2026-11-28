<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Brokerage;

use App\Brokerage\Oversight;
use App\Http\Controllers\Controller;
use App\Http\Requests\Brokerage\OversightSettingsRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Agent oversight (A16.1, ADR 0076): agents needing help, deals at risk and
 * the agent's profile for the broker, with privacy rules.
 */
final class OversightController extends Controller
{
    /**
     * Agents needing help and deals at risk.
     *
     * Each agent with the reasons they may need help, and the deals with a key date close, missed, or no movement for 30 days. Counts and stages only: never clients, contacts or notes. A team leader sees their team.
     *
     * @response array{data: array{brokerage: string, scope: 'brokerage'|'team', team: string|null, settings: array{quiet_days: int, no_deal_days: int, new_days: int}, can_change: bool, counts: array{agents: int, need_help: int, deals_at_risk: int}, agents: list<array{id: int, name: string, team: string|null, goal: string, last_active: string|null, signals: list<'quiet'|'no_deal'|'ce_behind'|'plan_behind'|'deadline_missed'|'new'>, deals: array{active: int, under_contract: int, closed_90: int}, ce: array{due_on: string|null, hours_left: float, first_renewal: bool, complete: bool}|null}>, at_risk: list<array{id: string, agent: string, agent_id: int, stage: string, city: string|null, address: string|null, reasons: list<'date_soon'|'date_missed'|'stuck'>, next_date: array{kind: string, due_on: string}|null}>}}
     */
    public function index(#[CurrentUser] User $user, Oversight $oversight): JsonResponse
    {
        return response()->json(['data' => $oversight->board($user)]);
    }

    /**
     * An agent, as their broker sees them.
     *
     * Plan, activity this month, deals by stage (the address only under contract), licences and CE. Viewing is audited.
     *
     * @response array{data: array{agent: array{id: int, name: string, team: string|null, goal: string, last_active: string|null, signals: list<'quiet'|'no_deal'|'ce_behind'|'plan_behind'|'deadline_missed'|'new'>, deals: array{active: int, under_contract: int, closed_90: int}, ce: array{due_on: string|null, hours_left: float, first_renewal: bool, complete: bool}|null}, plan: array{goal: string, week: int, weeks: int, done: int, total: int}|null, activity: array{conversations: int, appointments: int, posts: int, ce_hours: float}, deals: list<array{id: string, stage: string, side: string, city: string|null, address: string|null, closed_at: string|null}>, licences: list<array{state: string, number: string, status: string, expires_on: string|null}>}}
     */
    public function show(int $member, #[CurrentUser] User $user, Oversight $oversight): JsonResponse
    {
        return response()->json(['data' => $oversight->profile($user, $member)]);
    }

    /**
     * Change the "needs help" thresholds.
     *
     * The designated broker only.
     *
     * @response array{data: array{brokerage: string, scope: 'brokerage'|'team', team: string|null, settings: array{quiet_days: int, no_deal_days: int, new_days: int}, can_change: bool, counts: array{agents: int, need_help: int, deals_at_risk: int}, agents: list<array{id: int, name: string, team: string|null, goal: string, last_active: string|null, signals: list<'quiet'|'no_deal'|'ce_behind'|'plan_behind'|'deadline_missed'|'new'>, deals: array{active: int, under_contract: int, closed_90: int}, ce: array{due_on: string|null, hours_left: float, first_renewal: bool, complete: bool}|null}>, at_risk: list<array{id: string, agent: string, agent_id: int, stage: string, city: string|null, address: string|null, reasons: list<'date_soon'|'date_missed'|'stuck'>, next_date: array{kind: string, due_on: string}|null}>}}
     */
    public function settings(OversightSettingsRequest $request, #[CurrentUser] User $user, Oversight $oversight): JsonResponse
    {
        return response()->json(['data' => $oversight->setSettings($user, $request->settings())]);
    }

    /**
     * What my broker sees.
     *
     * My own profile exactly as my broker sees it, or null when I'm not in a brokerage.
     *
     * @response array{data: array{agent: array{id: int, name: string, team: string|null, goal: string, last_active: string|null, signals: list<'quiet'|'no_deal'|'ce_behind'|'plan_behind'|'deadline_missed'|'new'>, deals: array{active: int, under_contract: int, closed_90: int}, ce: array{due_on: string|null, hours_left: float, first_renewal: bool, complete: bool}|null}, plan: array{goal: string, week: int, weeks: int, done: int, total: int}|null, activity: array{conversations: int, appointments: int, posts: int, ce_hours: float}, deals: list<array{id: string, stage: string, side: string, city: string|null, address: string|null, closed_at: string|null}>, licences: list<array{state: string, number: string, status: string, expires_on: string|null}>}|null}
     */
    public function mine(#[CurrentUser] User $user, Oversight $oversight): JsonResponse
    {
        return response()->json(['data' => $oversight->mine($user)]);
    }
}
