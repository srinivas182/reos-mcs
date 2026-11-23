<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Practice;

use App\Http\Controllers\Controller;
use App\Models\Challenge;
use App\Models\User;
use App\Practice\Challenges;
use App\Practice\PracticeAccess;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/**
 * Challenges (A9.3b, candidate screen 52): challenge a friend with 10
 * questions by share link; whoever accepts answers the same 10. First
 * names only.
 */
final class ChallengeController extends Controller
{
    /**
     * My challenges.
     *
     * The ones I sent and the ones I accepted, newest first, each with its board.
     *
     * @response array{data: list<array{uuid: string, link: string, from: string, mine: bool, questions: int, expires_at: string, expired: bool, attempt_id: int|null, submitted: bool, entries: list<array{first_name: string, correct: int, total: int, hints: int, points: int, mine: bool, finished: bool}>}>}
     */
    public function index(#[CurrentUser] User $user, PracticeAccess $access, Challenges $challenges): JsonResponse
    {
        $access->allow($user);

        return response()->json(['data' => $challenges->mine($user)]);
    }

    /**
     * Challenge a friend.
     *
     * Picks 10 questions and starts my own attempt; share the link. It works for 7 days.
     *
     * @status 201
     *
     * @response array{data: array{uuid: string, link: string, from: string, mine: bool, questions: int, expires_at: string, expired: bool, attempt_id: int|null, submitted: bool, entries: list<array{first_name: string, correct: int, total: int, hints: int, points: int, mine: bool, finished: bool}>}}
     */
    public function store(#[CurrentUser] User $user, PracticeAccess $access, Challenges $challenges): JsonResponse
    {
        $access->allow($user);

        return response()->json(['data' => $challenges->board($challenges->create($user), $user)], 201);
    }

    /**
     * A challenge.
     *
     * Who sent it and how many questions; the board once I've accepted (first names only).
     *
     * @response array{data: array{uuid: string, link: string, from: string, mine: bool, questions: int, expires_at: string, expired: bool, attempt_id: int|null, submitted: bool, entries: list<array{first_name: string, correct: int, total: int, hints: int, points: int, mine: bool, finished: bool}>}}
     */
    public function show(string $challenge, #[CurrentUser] User $user, PracticeAccess $access, Challenges $challenges): JsonResponse
    {
        $access->allow($user);
        $found = Challenge::query()->where('uuid', $challenge)->firstOrFail();
        $board = $challenges->board($found, $user);

        return response()->json(['data' => $challenges->takesPart($user, $found) ? $board : ['entries' => []] + $board]);
    }

    /**
     * Accept a challenge.
     *
     * Starts (or carries on) my attempt at the same 10 questions.
     *
     * @response array{data: array{uuid: string, link: string, from: string, mine: bool, questions: int, expires_at: string, expired: bool, attempt_id: int|null, submitted: bool, entries: list<array{first_name: string, correct: int, total: int, hints: int, points: int, mine: bool, finished: bool}>}}
     */
    public function accept(string $challenge, #[CurrentUser] User $user, PracticeAccess $access, Challenges $challenges): JsonResponse
    {
        $access->allow($user);
        $found = Challenge::query()->where('uuid', $challenge)->firstOrFail();
        $challenges->accept($user, $found);

        return response()->json(['data' => $challenges->board($found, $user)]);
    }
}
