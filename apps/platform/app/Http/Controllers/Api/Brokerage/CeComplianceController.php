<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Brokerage;

use App\Brokerage\CeCompliance;
use App\Brokerage\Sponsorships;
use App\Http\Controllers\Controller;
use App\Http\Requests\Brokerage\AssignCourseRequest;
use App\Http\Requests\Brokerage\ReleaseRequest;
use App\Http\Requests\Brokerage\RemindRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * CE compliance and sponsorship (A16.2, ADR 0077): every agent's renewal and
 * SAE status, remind all due agents, assign a course, and sponsorship join
 * and release with a handover. Needs "sponsor agents" (A15.2).
 */
final class CeComplianceController extends Controller
{
    /**
     * CE compliance.
     *
     * Every agent's licence, renewal date, hours left and SAE status, the courses that can be assigned, and when each agent was last reminded.
     *
     * @response array{data: array{brokerage: string, scope: 'brokerage'|'team', team: string|null, counts: array{agents: int, due: int, done: int}, rows: list<array{id: int, name: string, team: string|null, licence: string|null, status: 'done'|'on_track'|'due'|'behind'|'overdue'|'no_licence', due_on: string|null, days_left: int|null, hours_required: float, hours_done: float, hours_left: float, sae: bool, reminded_at: string|null, assignments: list<array{course: string, due_on: string|null, done: bool}>}>, courses: list<array{id: int, title: string, type: string, hours: string|null}>}}
     */
    public function index(#[CurrentUser] User $user, CeCompliance $ce): JsonResponse
    {
        return response()->json(['data' => $ce->table($user)]);
    }

    /**
     * Remind all due agents.
     *
     * One action: agents due, behind or overdue who weren't reminded in the last 7 days get a reminder from the broker.
     *
     * @response array{data: array{sent: int, skipped: int, table: array{brokerage: string, scope: 'brokerage'|'team', team: string|null, counts: array{agents: int, due: int, done: int}, rows: list<array{id: int, name: string, team: string|null, licence: string|null, status: 'done'|'on_track'|'due'|'behind'|'overdue'|'no_licence', due_on: string|null, days_left: int|null, hours_required: float, hours_done: float, hours_left: float, sae: bool, reminded_at: string|null, assignments: list<array{course: string, due_on: string|null, done: bool}>}>, courses: list<array{id: int, title: string, type: string, hours: string|null}>}}}
     */
    public function remind(RemindRequest $request, #[CurrentUser] User $user, CeCompliance $ce): JsonResponse
    {
        return response()->json(['data' => $ce->remindDue($user)]);
    }

    /**
     * Assign a course.
     *
     * A live CE, qualifying or elective course for some agents or everyone due, with an optional due date.
     *
     * @response array{data: array{assigned: int, table: array{brokerage: string, scope: 'brokerage'|'team', team: string|null, counts: array{agents: int, due: int, done: int}, rows: list<array{id: int, name: string, team: string|null, licence: string|null, status: 'done'|'on_track'|'due'|'behind'|'overdue'|'no_licence', due_on: string|null, days_left: int|null, hours_required: float, hours_done: float, hours_left: float, sae: bool, reminded_at: string|null, assignments: list<array{course: string, due_on: string|null, done: bool}>}>, courses: list<array{id: int, title: string, type: string, hours: string|null}>}}}
     */
    public function assign(AssignCourseRequest $request, #[CurrentUser] User $user, CeCompliance $ce): JsonResponse
    {
        $due = $request->input('due_on');

        return response()->json(['data' => $ce->assign($user, $request->integer('course_id'), $request->agents(), is_string($due) ? $due : null)]);
    }

    /**
     * Sponsorships.
     *
     * Joins and releases with their TREC confirmation, the agents who can be released, and who can take a handover.
     *
     * @response array{data: array{brokerage: string, trec_number: string|null, changes: list<array{id: string, kind: 'join'|'release', agent: string, agent_id: int, effective_on: string, handover_to: string|null, deals: int, status: 'recorded'|'done'|'confirmed', confirmed_at: string|null}>, agents: list<array{id: int, name: string, open_deals: int}>, handover: list<array{id: int, name: string}>}}
     */
    public function sponsorships(#[CurrentUser] User $user, Sponsorships $sponsorships): JsonResponse
    {
        return response()->json(['data' => $sponsorships->board($user)]);
    }

    /**
     * Release an agent.
     *
     * On a date up to 30 days ahead, handing their open deals to another agent or broker of the brokerage. Their role ends on the date; TREC's file confirms it later.
     *
     * @response array{data: array{brokerage: string, trec_number: string|null, changes: list<array{id: string, kind: 'join'|'release', agent: string, agent_id: int, effective_on: string, handover_to: string|null, deals: int, status: 'recorded'|'done'|'confirmed', confirmed_at: string|null}>, agents: list<array{id: int, name: string, open_deals: int}>, handover: list<array{id: int, name: string}>}}
     */
    public function release(ReleaseRequest $request, #[CurrentUser] User $user, Sponsorships $sponsorships): JsonResponse
    {
        $to = $request->input('handover_to');

        return response()->json(['data' => $sponsorships->release($user, $request->integer('agent_id'), $request->string('effective_on')->toString(), is_numeric($to) ? (int) $to : null)]);
    }
}
