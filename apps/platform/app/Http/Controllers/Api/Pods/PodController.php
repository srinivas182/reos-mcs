<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Pods;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Pods\Pods;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** My accountability pod (A13.2, ADR 0054; agent screen 131). */
final class PodController extends Controller
{
    /**
     * My pod.
     *
     * `none`, `waiting` (matched with agents at my brokerage with the same goal, language and part-time flag) or `member`: the weekly call, the pod's conversation, and each member's goal for the week and the numbers they share, only inside the pod.
     *
     * @response array{data: array{status: 'none'|'waiting'|'member', waiting_since: string|null, pod: array{id: string, name: string, goal: string, call: array{day: string, time: string, timezone: string, next: string|null, session: string|null}, conversation: string|null, week_of: string, members: list<array{id: int, name: string, me: bool, goal: string|null, metrics: list<array{key: string, done: float|int, target: float|int}>, shares: list<string>}>}|null}}
     */
    public function show(#[CurrentUser] User $user, Pods $pods): JsonResponse
    {
        return response()->json(['data' => $pods->mine($user)]);
    }

    /**
     * Join a pod.
     *
     * Into an open pod now, or onto the waiting list until five (or three after a week) can make one.
     *
     * @response array{data: array{status: 'none'|'waiting'|'member', waiting_since: string|null, pod: array{id: string, name: string, goal: string, call: array{day: string, time: string, timezone: string, next: string|null, session: string|null}, conversation: string|null, week_of: string, members: list<array{id: int, name: string, me: bool, goal: string|null, metrics: list<array{key: string, done: float|int, target: float|int}>, shares: list<string>}>}|null}}
     */
    public function join(#[CurrentUser] User $user, Pods $pods): JsonResponse
    {
        $pods->join($user);

        return response()->json(['data' => $pods->mine($user)]);
    }

    /**
     * Leave the pod or the waiting list.
     *
     * `left` is the pod to rejoin with Undo.
     *
     * @response array{data: array{status: 'none'|'waiting'|'member', waiting_since: string|null, pod: array{id: string, name: string, goal: string, call: array{day: string, time: string, timezone: string, next: string|null, session: string|null}, conversation: string|null, week_of: string, members: list<array{id: int, name: string, me: bool, goal: string|null, metrics: list<array{key: string, done: float|int, target: float|int}>, shares: list<string>}>}|null}, left: string|null}
     */
    public function leave(#[CurrentUser] User $user, Pods $pods): JsonResponse
    {
        $left = $pods->leave($user);

        return response()->json(['data' => $pods->mine($user), 'left' => $left]);
    }

    /**
     * Rejoin (Undo).
     *
     * @response array{data: array{status: 'none'|'waiting'|'member', waiting_since: string|null, pod: array{id: string, name: string, goal: string, call: array{day: string, time: string, timezone: string, next: string|null, session: string|null}, conversation: string|null, week_of: string, members: list<array{id: int, name: string, me: bool, goal: string|null, metrics: list<array{key: string, done: float|int, target: float|int}>, shares: list<string>}>}|null}}
     */
    public function rejoin(Request $request, #[CurrentUser] User $user, Pods $pods): JsonResponse
    {
        $request->validate(['pod' => ['required', 'uuid']]);
        $pods->rejoin($user, $request->string('pod')->toString());

        return response()->json(['data' => $pods->mine($user)]);
    }

    /**
     * My goal for the week.
     *
     * @response array{data: array{status: 'none'|'waiting'|'member', waiting_since: string|null, pod: array{id: string, name: string, goal: string, call: array{day: string, time: string, timezone: string, next: string|null, session: string|null}, conversation: string|null, week_of: string, members: list<array{id: int, name: string, me: bool, goal: string|null, metrics: list<array{key: string, done: float|int, target: float|int}>, shares: list<string>}>}|null}}
     */
    public function goal(Request $request, #[CurrentUser] User $user, Pods $pods): JsonResponse
    {
        $request->validate(['text' => ['required', 'string', 'min:2', 'max:200']]);
        $pods->setGoal($user, $request->string('text')->toString());

        return response()->json(['data' => $pods->mine($user)]);
    }

    /**
     * What I share inside the pod.
     *
     * @response array{data: array{status: 'none'|'waiting'|'member', waiting_since: string|null, pod: array{id: string, name: string, goal: string, call: array{day: string, time: string, timezone: string, next: string|null, session: string|null}, conversation: string|null, week_of: string, members: list<array{id: int, name: string, me: bool, goal: string|null, metrics: list<array{key: string, done: float|int, target: float|int}>, shares: list<string>}>}|null}}
     */
    public function share(Request $request, #[CurrentUser] User $user, Pods $pods): JsonResponse
    {
        $request->validate(['metrics' => ['present', 'array'], 'metrics.*' => ['in:'.implode(',', Pods::SHARE)]]);
        /** @var list<string> $metrics */
        $metrics = array_values(array_filter((array) $request->input('metrics', []), is_string(...)));
        $pods->share($user, $metrics);

        return response()->json(['data' => $pods->mine($user)]);
    }
}
