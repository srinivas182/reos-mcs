<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Practice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Practice\MathCheckRequest;
use App\Models\User;
use App\Practice\MathLab\MathLab;
use App\Practice\PracticeAccess;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Math lab (candidate screen 48, A9.2c). */
final class MathLabController extends Controller
{
    /**
     * The math lab.
     *
     * Every kind of problem with how many the candidate has solved.
     *
     * @response array{data: list<array{kind: string, solved: int}>}
     */
    public function index(#[CurrentUser] User $user, PracticeAccess $access, MathLab $lab): JsonResponse
    {
        $access->allow($user);

        return response()->json(['data' => $lab->menu($user)]);
    }

    /**
     * A new problem.
     *
     * New numbers of the kind asked for: the problem and its steps, without
     * the answers (the server checks each step).
     *
     * @status 201
     *
     * @response array{data: array{kind: string, seed: int, prompt: string, steps: list<array{n: int, label: string, unit: string}>}}
     */
    public function store(Request $request, #[CurrentUser] User $user, PracticeAccess $access, MathLab $lab): JsonResponse
    {
        $access->allow($user);
        $kind = $request->validate(['kind' => ['required', Rule::in($lab->kinds())]])['kind'];

        return response()->json(['data' => $lab->problem(is_string($kind) ? $kind : '')->toArray()], 201);
    }

    /**
     * Check a step.
     *
     * Right: the answer as shown, and whether the problem is solved. Wrong:
     * the step's hint. `reveal` shows the answer instead. Steps go in order.
     *
     * @response array{data: array{correct: bool, hint: string|null, answer: string|null, solved: bool}}
     */
    public function check(MathCheckRequest $request, #[CurrentUser] User $user, PracticeAccess $access, MathLab $lab): JsonResponse
    {
        $access->allow($user);

        return response()->json(['data' => $lab->check($user, $request->kind(), $request->seed(), $request->step(), $request->value(), $request->reveal())]);
    }
}
