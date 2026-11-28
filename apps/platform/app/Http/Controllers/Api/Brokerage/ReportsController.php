<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Brokerage;

use App\Brokerage\BrokerBriefs;
use App\Brokerage\Reports;
use App\Http\Controllers\Controller;
use App\Http\Requests\Brokerage\ReportPeriodRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Brokerage reports and the broker's morning brief (A16.3, ADR 0078).
 * A team leader sees their own team.
 */
final class ReportsController extends Controller
{
    /**
     * Closings report.
     *
     * Closings and volume by agent, team and month for the period, matched to the transaction tool: each connected tool's closings with its last sync, and closings recorded only in REOS listed as differences.
     *
     * @response array{data: array{brokerage: string, scope: 'brokerage'|'team', team: string|null, period: 'this_month'|'last_month'|'this_quarter'|'this_year'|'last_year', from: string, to: string, totals: array{count: int, volume: int}, by_agent: list<array{id: int, name: string, team: string|null, count: int, volume: int}>, by_team: list<array{name: string|null, count: int, volume: int}>, by_month: list<array{month: string, count: int, volume: int}>, tools: list<array{connection: int, tool: string, name: string, label: string|null, last_synced_at: string|null, stale: bool, count: int, volume: int}>, reos_only: array{count: int, volume: int, deals: list<array{id: string, agent: string, property: string, price: int|null, closed_at: string}>}}}
     */
    public function closings(ReportPeriodRequest $request, #[CurrentUser] User $user, Reports $reports): JsonResponse
    {
        return response()->json(['data' => $reports->closings($user, $request->period())]);
    }

    /**
     * Training report.
     *
     * Each course the brokerage assigned: how many agents, done and overdue, with CE hours for CE courses.
     *
     * @response array{data: array{brokerage: string, scope: 'brokerage'|'team', team: string|null, courses: list<array{id: int, title: string, type: string, ce_hours: string|null, assigned: int, done: int, overdue: int}>}}
     */
    public function training(#[CurrentUser] User $user, Reports $reports): JsonResponse
    {
        return response()->json(['data' => $reports->training($user)]);
    }

    /**
     * The broker's morning brief.
     *
     * Agents who need help, deals at risk, approvals waiting, agents due for CE (null without "sponsor agents") and closings this month. Counts and stages only.
     *
     * @response array{data: array{date: string, brokerage: string, scope: 'brokerage'|'team', team: string|null, settings: array{quiet_days: int, no_deal_days: int, new_days: int}, need_help: array{count: int, agents: list<array{id: int, name: string, signals: list<string>}>}, at_risk: array{count: int, deals: list<array{id: string, agent: string, stage: string, city: string|null, reasons: list<string>}>}, approvals: array{waiting: int, overdue: int}, ce_due: int|null, closings: array{count: int, volume: int}}}
     */
    public function brief(#[CurrentUser] User $user, BrokerBriefs $briefs): JsonResponse
    {
        return response()->json(['data' => $briefs->for($user)]);
    }
}
