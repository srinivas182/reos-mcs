<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Rewards;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rewards\DailyGoals;
use App\Rewards\Rewards;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Daily goals, points and rewards (A13.3a, ADR 0055; agent screens 111 and 112). */
final class RewardsController extends Controller
{
    /**
     * Today's goals.
     *
     * The day's goals for my mode, what's done, the points earned (awarded as goals are met), my streak and any brokerage bonus campaigns.
     *
     * @response array{data: array{day: string, mode: string, goals: list<array{key: 'conversations'|'ce_lesson'|'follow_ups'|'post', done: int, target: int, points: int, met: bool, bonus: int}>, points_today: int, bonus_today: int, all_done: bool, all_done_points: int, streak: int, best_streak: int, balance: int, campaigns: list<array{id: string, title: string, goal: string, multiplier: int, ends_on: string}>}}
     */
    public function today(#[CurrentUser] User $user, DailyGoals $goals): JsonResponse
    {
        return response()->json(['data' => $goals->today($user)]);
    }

    /**
     * My rewards.
     *
     * My points balance and its value (1,000 points = $10 inside REOS, no cash value), what points pay for, and recent points.
     *
     * @response array{data: array{balance: int, value_cents: int, options: list<array{price: int, kind: 'credit_pack'|'plan', name: string, points: int, credits: int|null, interval: 'once'|'month'|'year', affordable: bool}>, history: list<array{reason: string, points: int, memo: string|null, at: string}>}}
     */
    public function index(#[CurrentUser] User $user, Rewards $rewards): JsonResponse
    {
        return response()->json(['data' => $rewards->summary($user)]);
    }

    /**
     * Use points.
     *
     * Pays for an AI credit pack or a period of membership with points (mentor sessions are paid when booking).
     *
     * @response array{data: array{balance: int, value_cents: int, options: list<array{price: int, kind: 'credit_pack'|'plan', name: string, points: int, credits: int|null, interval: 'once'|'month'|'year', affordable: bool}>, history: list<array{reason: string, points: int, memo: string|null, at: string}>}, order: string}
     */
    public function redeem(Request $request, #[CurrentUser] User $user, Rewards $rewards): JsonResponse
    {
        $request->validate(['price' => ['required', 'integer']]);
        $order = $rewards->redeem($user, $request->integer('price'));

        return response()->json(['data' => $rewards->summary($user), 'order' => $order->uuid]);
    }
}
