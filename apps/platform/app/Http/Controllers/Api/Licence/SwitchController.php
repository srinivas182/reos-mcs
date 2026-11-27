<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Licence;

use App\Http\Controllers\Controller;
use App\Licences\Journey\Mentors;
use App\Licences\Journey\SwitchToAgent;
use App\Mentoring\MentorProgram;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Switching to agent (A10.3c, ADR 0041; candidate f03 and 20) and the new
 * agent's mentors (f04).
 */
final class SwitchController extends Controller
{
    /**
     * My licence and switching to agent.
     *
     * The number entered, what the regulator's record says, whether REOS is checking daily, and the brokerage whose offer I accepted.
     *
     * @response array{data: array{number: string|null, check: 'verified'|'inactive'|'name_mismatch'|'not_found'|'no_lookup'|null, checked_at: string|null, watching: bool, record: array{name: string, type: string, status: string, licensed_on: string|null, sponsor: string|null}|null, can_switch: bool, broker: array{id: string, name: string}|null, switched: bool}}
     */
    public function show(#[CurrentUser] User $user, SwitchToAgent $switch): JsonResponse
    {
        return response()->json(['data' => $switch->show($user)]);
    }

    /**
     * Check my licence number.
     *
     * Against the regulator's record; it must be active and in my name. If it isn't active yet, REOS checks daily and tells me.
     *
     * @response array{data: array{number: string|null, check: 'verified'|'inactive'|'name_mismatch'|'not_found'|'no_lookup'|null, checked_at: string|null, watching: bool, record: array{name: string, type: string, status: string, licensed_on: string|null, sponsor: string|null}|null, can_switch: bool, broker: array{id: string, name: string}|null, switched: bool}}
     */
    public function check(Request $request, #[CurrentUser] User $user, SwitchToAgent $switch): JsonResponse
    {
        $request->validate(['number' => ['required', 'string', 'min:4', 'max:40']]);
        $switch->check($user, $request->string('number')->trim()->toString());

        return response()->json(['data' => $switch->show($user)]);
    }

    /**
     * Switch to agent.
     *
     * Same account. One way in: `broker` (the brokerage whose offer I accepted), `association` (a sponsor's code) or `alone`.
     *
     * @response array{data: array{number: string|null, check: 'verified'|'inactive'|'name_mismatch'|'not_found'|'no_lookup'|null, checked_at: string|null, watching: bool, record: array{name: string, type: string, status: string, licensed_on: string|null, sponsor: string|null}|null, can_switch: bool, broker: array{id: string, name: string}|null, switched: bool}, organisation: string}
     */
    public function switch(Request $request, #[CurrentUser] User $user, SwitchToAgent $switch): JsonResponse
    {
        $request->validate(['path' => ['required', Rule::in(SwitchToAgent::PATHS)], 'code' => ['nullable', 'string', 'max:40']]);
        $path = $request->string('path')->toString();
        $organisation = $switch->switch($user, $path === 'broker' ? 'broker' : ($path === 'association' ? 'association' : 'alone'), $request->filled('code') ? $request->string('code')->toString() : null);

        return response()->json(['data' => $switch->show($user), 'organisation' => $organisation->name]);
    }

    /**
     * My mentors.
     *
     * Mentors of my own brokerage who take 1-on-1 bookings, with rating, languages and next free hour. My assigned mentor comes first and is free; `points` says whether a mentor accepts points, and `points_balance` is mine (1 point = 1 cent inside REOS).
     *
     * @response array{data: array{brokerage: string|null, mentors: list<array{id: int, user_id: int, name: string, school: string|null, bio: string|null, languages: list<string>, topics: list<string>, price_30: int, price_60: int, rating: float|null, ratings: int, next_slot: string|null, assigned: bool, free: bool, points: bool}>, assigned: int|null, change_requested: bool, points_balance: int}}
     */
    public function mentors(#[CurrentUser] User $user, Mentors $mentors): JsonResponse
    {
        return response()->json(['data' => $mentors->for($user)]);
    }

    /**
     * Ask for another mentor.
     *
     * The brokerage's brokers are told; my mentor stays until they assign another.
     *
     * @response array{data: array{brokerage: string|null, mentors: list<array{id: int, user_id: int, name: string, school: string|null, bio: string|null, languages: list<string>, topics: list<string>, price_30: int, price_60: int, rating: float|null, ratings: int, next_slot: string|null, assigned: bool, free: bool, points: bool}>, assigned: int|null, change_requested: bool, points_balance: int}}
     */
    public function changeMentor(#[CurrentUser] User $user, Mentors $mentors, MentorProgram $program): JsonResponse
    {
        $program->requestChange($user);

        return response()->json(['data' => $mentors->for($user)]);
    }
}
