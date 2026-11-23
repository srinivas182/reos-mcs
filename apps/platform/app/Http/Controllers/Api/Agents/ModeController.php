<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Agents;

use App\Agents\AgentModes;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agents\FlagsRequest;
use App\Http\Requests\Agents\GoalRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * My goal (A11.1a, ADR 0044; agent screens 4, 5, 6, 146): Launch, Grow,
 * Lead or Own, work-style flags, suggested switches.
 */
final class ModeController extends Controller
{
    /**
     * My goal.
     *
     * The goal (null until set), flags, whether Own is unlocked (and when), a suggested switch, the history, and what the welcome shows.
     *
     * @response array{data: array{goal: string|null, flags: list<string>, needs_setup: bool, suggested_default: string, own: array{unlocked: bool, unlocks_on: string|null, years: int}, suggestion: array{goal: string, closings: int}|null, history: list<array{from: string|null, to: string, reason: string, at: string}>, welcome: array{name: string, licence: string|null, state: string|null, brokerage: string|null, verified: bool}}}
     */
    public function show(#[CurrentUser] User $user, AgentModes $modes): JsonResponse
    {
        return response()->json(['data' => $modes->show($user)]);
    }

    /**
     * Set my goal.
     *
     * `goal`: launch, grow, lead or own (Own only when unlocked). `reason`: setup, manual or suggested.
     *
     * @response array{data: array{goal: string|null, flags: list<string>, needs_setup: bool, suggested_default: string, own: array{unlocked: bool, unlocks_on: string|null, years: int}, suggestion: array{goal: string, closings: int}|null, history: list<array{from: string|null, to: string, reason: string, at: string}>, welcome: array{name: string, licence: string|null, state: string|null, brokerage: string|null, verified: bool}}}
     */
    public function goal(GoalRequest $request, #[CurrentUser] User $user, AgentModes $modes): JsonResponse
    {
        return response()->json(['data' => $modes->choose($user, $request->string('goal')->toString(), $request->reason())]);
    }

    /**
     * Set my work-style flags.
     *
     * Any of part_time, bilingual, specialist, referral_only, returning.
     *
     * @response array{data: array{goal: string|null, flags: list<string>, needs_setup: bool, suggested_default: string, own: array{unlocked: bool, unlocks_on: string|null, years: int}, suggestion: array{goal: string, closings: int}|null, history: list<array{from: string|null, to: string, reason: string, at: string}>, welcome: array{name: string, licence: string|null, state: string|null, brokerage: string|null, verified: bool}}}
     */
    public function flags(FlagsRequest $request, #[CurrentUser] User $user, AgentModes $modes): JsonResponse
    {
        return response()->json(['data' => $modes->setFlags($user, $request->flags())]);
    }

    /**
     * Not yet.
     *
     * The suggested switch waits 30 days.
     *
     * @response array{data: array{goal: string|null, flags: list<string>, needs_setup: bool, suggested_default: string, own: array{unlocked: bool, unlocks_on: string|null, years: int}, suggestion: array{goal: string, closings: int}|null, history: list<array{from: string|null, to: string, reason: string, at: string}>, welcome: array{name: string, licence: string|null, state: string|null, brokerage: string|null, verified: bool}}}
     */
    public function snooze(#[CurrentUser] User $user, AgentModes $modes): JsonResponse
    {
        return response()->json(['data' => $modes->snooze($user)]);
    }
}
