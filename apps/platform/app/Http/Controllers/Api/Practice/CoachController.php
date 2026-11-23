<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Practice;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Practice\Drills;
use App\Practice\PracticeAccess;
use App\Practice\PracticeCoach;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** The practice hub's ranking and the smart drill's Hint (candidate screens 38 and 47, A9.2b). */
final class CoachController extends Controller
{
    /**
     * What to practise next.
     *
     * The smart drill (the three weakest topics, how many recent misses it
     * will bring back, and any drill still open), fix plans under way, and
     * every practice mode ranked by what helps most, each with why.
     *
     * @response array{data: array{drill: array{topics: list<string>, questions: int, misses: int, open: int|null}, plans: list<array{id: int, topic: string, done: int, steps: int}>, ranked: list<array{mode: 'diagnostic'|'plan'|'drill'|'fix'|'simulation'|'readiness', why: string, topic: string|null, plan_id: int|null}>}}
     */
    public function hub(#[CurrentUser] User $user, PracticeAccess $access, PracticeCoach $coach): JsonResponse
    {
        $access->allow($user);

        return response()->json(['data' => $coach->hub($user)]);
    }

    /**
     * Hint.
     *
     * In a smart drill, before answering: one wrong option to strike out, a
     * nudge that never gives the answer. Hints are kept, and cost points only
     * in challenges.
     *
     * @response array{data: array{eliminated: int}}
     */
    public function hint(int $attempt, int $question, #[CurrentUser] User $user, PracticeAccess $access, Drills $drills): JsonResponse
    {
        return response()->json(['data' => $drills->hint($access->attempt($user, $attempt), $question)]);
    }
}
