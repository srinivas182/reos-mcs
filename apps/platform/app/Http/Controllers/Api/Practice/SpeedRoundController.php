<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Practice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Practice\FinishSpeedRoundRequest;
use App\Models\SpeedRound;
use App\Models\User;
use App\Practice\PracticeAccess;
use App\Practice\SpeedRounds;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** The speed round (candidate screen 50, A9.2d). */
final class SpeedRoundController extends Controller
{
    /**
     * Start a speed round.
     *
     * 60 seconds and 40 true-or-false statements (a question and one of
     * its options: is it the answer?), with the best score so far.
     *
     * @status 201
     *
     * @response array{data: array{id: int, seconds: int, best: int|null, statements: list<array{id: int, prompt: string, answer: string, truth: bool, topic: string}>}}
     */
    public function store(#[CurrentUser] User $user, PracticeAccess $access, SpeedRounds $rounds): JsonResponse
    {
        $access->allow($user);

        return response()->json(['data' => $rounds->start($user, $user->locale->value)], 201);
    }

    /**
     * Finish a speed round.
     *
     * The taps (each statement answered True or False). The server scores
     * them, keeps the best score, and puts the misses into flashcards.
     *
     * @response array{data: array{score: int, answered: int, best: int, record: bool, misses: int}}
     */
    public function finish(int $round, FinishSpeedRoundRequest $request, #[CurrentUser] User $user, PracticeAccess $access, SpeedRounds $rounds): JsonResponse
    {
        $access->allow($user);
        $found = SpeedRound::query()->where('user_id', $user->id)->findOrFail($round);

        return response()->json(['data' => $rounds->finish($found, $request->taps(), $user->locale->value)]);
    }
}
