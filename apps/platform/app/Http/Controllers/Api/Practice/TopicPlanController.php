<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Practice;

use App\Http\Controllers\Controller;
use App\Models\TopicPlan;
use App\Models\User;
use App\Practice\DiagnosticBank;
use App\Practice\PracticeAccess;
use App\Practice\TopicPlans;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Fix a weak topic (candidate screen 46, A9.2b). */
final class TopicPlanController extends Controller
{
    /**
     * Fix a weak topic.
     *
     * The candidate's plan for the topic still under way, or a new one
     * built from their own misses: micro-lessons at the right second, the
     * misses with why, practice, and a retest with new questions.
     *
     * @status 201
     *
     * @response array{data: array{id: int, topic: string, now: int|null, start: int|null, goal: int, weak: bool, minutes: int, completed: bool, steps: list<array{n: int, kind: string, minutes: int, status: 'done'|'current'|'todo', lesson: array{id: int, title: string, seconds: int|null}|null, attempt_id: int|null, results_id: int|null}>, misses: list<array{id: int, prompt: string, options: list<string>, right: int, explanation: string, source: string}>}}
     */
    public function store(Request $request, #[CurrentUser] User $user, PracticeAccess $access, TopicPlans $plans): JsonResponse
    {
        $access->allow($user);
        $topic = $request->validate(['topic' => ['required', Rule::in(array_keys(DiagnosticBank::WEIGHTS))]])['topic'];
        $plan = $plans->for($user, is_string($topic) ? $topic : '');

        return response()->json(['data' => $plans->show($plan, $user->locale->value)], $plan->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * A fix plan.
     *
     * Now against the 70% goal, each step with its minutes and whether it's
     * done, and the misses with why.
     *
     * @response array{data: array{id: int, topic: string, now: int|null, start: int|null, goal: int, weak: bool, minutes: int, completed: bool, steps: list<array{n: int, kind: string, minutes: int, status: 'done'|'current'|'todo', lesson: array{id: int, title: string, seconds: int|null}|null, attempt_id: int|null, results_id: int|null}>, misses: list<array{id: int, prompt: string, options: list<string>, right: int, explanation: string, source: string}>}}
     */
    public function show(int $plan, #[CurrentUser] User $user, PracticeAccess $access, TopicPlans $plans): JsonResponse
    {
        return response()->json(['data' => $plans->show(self::own($user, $plan, $access), $user->locale->value)]);
    }

    /**
     * Continue a fix plan.
     *
     * Starts the next step and says where to go: a lesson at its second,
     * the misses (shown on the plan), or the step's test (started, or the
     * one still open).
     *
     * @response array{data: array{kind: string, lesson: array{id: int, seconds: int|null}|null, attempt_id: int|null}}
     */
    public function continue(int $plan, #[CurrentUser] User $user, PracticeAccess $access, TopicPlans $plans): JsonResponse
    {
        return response()->json(['data' => $plans->continue(self::own($user, $plan, $access))]);
    }

    public static function own(User $user, int $id, PracticeAccess $access): TopicPlan
    {
        $access->allow($user);

        return TopicPlan::query()->where('user_id', $user->id)->findOrFail($id);
    }
}
