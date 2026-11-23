<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Agents;

use App\Agents\AgentModes;
use App\Agents\AgentPlans;
use App\Agents\AgentToday;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agents\PlanTaskRequest;
use App\Models\User;
use App\Support\PersonTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Today, the 90-day plan and the weekly scorecard for agents (A11.1b, ADR
 * 0044; agent screens 10, 14, 15, 110).
 */
final class TodayController extends Controller
{
    /**
     * Today.
     *
     * One ranked list from every tool (overdue, deadlines within 72 hours, today's appointments, follow-ups, plan tasks), each with its source; the mode's stats; and, until 20 contacts or a connected tool, the day-one steps.
     *
     * @response array{data: array{goal: string, stats: list<array{key: string, value: int, target: float|int|null}>, items: list<array{key: string, kind: 'todo'|'deadline'|'event'|'plan', reason: 'overdue'|'deadline'|'appointment'|'follow_up'|'plan', title: string, detail: string|null, source: string, at: string|null, all_day: bool, todo_id: int|null, deal: string|null, plan: array{code: string, week: int}|null}>, more: int, day_one: list<array{code: string, minutes: int, done: bool, link: string}>|null, plan_week: int}}
     */
    public function today(Request $request, #[CurrentUser] User $user, AgentToday $today): JsonResponse
    {
        [$from, $to] = PersonTimezone::today($request);

        return response()->json(['data' => $today->for($user, $from, $to)]);
    }

    /**
     * My 90-day plan.
     *
     * Thirteen weeks in phases for my goal, this week's tasks, and weekly targets (halved for part-time).
     *
     * @response array{data: array{goal: string, starts_on: string, ends_on: string, week: int, weeks: int, phases: list<array{code: string, from: int, to: int, current: bool}>, targets: array<string, float|int>, tasks: list<array{code: string, week: int, done: bool, auto: bool, repeats: bool, link: string|null}>, done: int, total: int, promised: bool}}
     */
    public function plan(#[CurrentUser] User $user, AgentPlans $plans): JsonResponse
    {
        return response()->json(['data' => $plans->current($this->agent($user))]);
    }

    /**
     * Tick a plan task.
     *
     * @response array{data: array{goal: string, starts_on: string, ends_on: string, week: int, weeks: int, phases: list<array{code: string, from: int, to: int, current: bool}>, targets: array<string, float|int>, tasks: list<array{code: string, week: int, done: bool, auto: bool, repeats: bool, link: string|null}>, done: int, total: int, promised: bool}}
     */
    public function tick(PlanTaskRequest $request, string $code, #[CurrentUser] User $user, AgentPlans $plans): JsonResponse
    {
        return response()->json(['data' => $plans->tick($this->agent($user), $code, $request->integer('week'), $request->boolean('done'))]);
    }

    /**
     * Make the First-Closing Promise.
     *
     * @response array{data: array{goal: string, starts_on: string, ends_on: string, week: int, weeks: int, phases: list<array{code: string, from: int, to: int, current: bool}>, targets: array<string, float|int>, tasks: list<array{code: string, week: int, done: bool, auto: bool, repeats: bool, link: string|null}>, done: int, total: int, promised: bool}}
     */
    public function promise(#[CurrentUser] User $user, AgentPlans $plans): JsonResponse
    {
        return response()->json(['data' => $plans->promise($this->agent($user))]);
    }

    /**
     * My weekly scorecard.
     *
     * Conversations, appointments, clients and CE this week against the plan's targets, and the one to fix.
     *
     * @response array{data: array{week_of: string, metrics: list<array{key: string, done: float|int, target: float|int}>, fix: string|null}}
     */
    public function scorecard(Request $request, #[CurrentUser] User $user, AgentToday $today): JsonResponse
    {
        return response()->json(['data' => $today->scorecard($user, CarbonImmutable::now(PersonTimezone::of($request)))]);
    }

    private function agent(User $user): User
    {
        app(AgentModes::class)->show($user);

        return $user;
    }
}
