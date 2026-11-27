<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Pods;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Pods\BrokerageChallenges;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** My brokerage's challenges with opt-in leaderboards (A13.2, ADR 0054). */
final class BrokerageChallengeController extends Controller
{
    /**
     * Challenges.
     *
     * My brokerage's challenges with the total and most-improved boards (only agents who joined appear).
     *
     * @response array{data: list<array{id: string, title: string, metric: 'conversations'|'appointments'|'ce_hours'|'posts', starts_on: string, ends_on: string, joined: bool, open: bool, entrants: int, total: list<array{id: int, name: string, me: bool, value: float|int, improved: float|int, personal_best: bool}>, improved: list<array{id: int, name: string, me: bool, value: float|int, improved: float|int, personal_best: bool}>}>}
     */
    public function index(#[CurrentUser] User $user, BrokerageChallenges $challenges): JsonResponse
    {
        return response()->json(['data' => $challenges->list($user)]);
    }

    /**
     * Join a challenge.
     *
     * My baseline is the same length of time just before it started.
     *
     * @response array{data: list<array{id: string, title: string, metric: 'conversations'|'appointments'|'ce_hours'|'posts', starts_on: string, ends_on: string, joined: bool, open: bool, entrants: int, total: list<array{id: int, name: string, me: bool, value: float|int, improved: float|int, personal_best: bool}>, improved: list<array{id: int, name: string, me: bool, value: float|int, improved: float|int, personal_best: bool}>}>}
     */
    public function join(string $challenge, #[CurrentUser] User $user, BrokerageChallenges $challenges): JsonResponse
    {
        $challenges->join($user, $challenge);

        return response()->json(['data' => $challenges->list($user)]);
    }

    /**
     * Leave a challenge.
     *
     * I leave its boards (Undo joins again).
     *
     * @response array{data: list<array{id: string, title: string, metric: 'conversations'|'appointments'|'ce_hours'|'posts', starts_on: string, ends_on: string, joined: bool, open: bool, entrants: int, total: list<array{id: int, name: string, me: bool, value: float|int, improved: float|int, personal_best: bool}>, improved: list<array{id: int, name: string, me: bool, value: float|int, improved: float|int, personal_best: bool}>}>}
     */
    public function leave(string $challenge, #[CurrentUser] User $user, BrokerageChallenges $challenges): JsonResponse
    {
        $challenges->leave($user, $challenge);

        return response()->json(['data' => $challenges->list($user)]);
    }
}
