<?php

declare(strict_types=1);

namespace App\Http\Controllers\Practice;

use App\Http\Controllers\Api\Practice\TopicPlanController;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Practice\DiagnosticBank;
use App\Practice\PracticeAccess;
use App\Practice\TopicPlans;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Fix a weak topic on the web (the phone's screen 46, A9.2b). */
final class TopicPlanPageController extends Controller
{
    public function store(Request $request, #[CurrentUser] User $user, PracticeAccess $access, TopicPlans $plans): RedirectResponse
    {
        $access->allow($user);
        $topic = $request->validate(['topic' => ['required', Rule::in(array_keys(DiagnosticBank::WEIGHTS))]])['topic'];

        return to_route('practice.plans.show', $plans->for($user, is_string($topic) ? $topic : '')->id);
    }

    public function show(int $plan, #[CurrentUser] User $user, PracticeAccess $access, TopicPlans $plans): Response
    {
        return Inertia::render('practice/fix', ['plan' => $plans->show(TopicPlanController::own($user, $plan, $access), $user->locale->value)]);
    }

    /** Continue: to the lesson at its second, the step's test, or back to the plan's misses. */
    public function continue(int $plan, #[CurrentUser] User $user, PracticeAccess $access, TopicPlans $plans): RedirectResponse
    {
        $found = TopicPlanController::own($user, $plan, $access);
        $next = $plans->continue($found);

        if ($next['kind'] === 'flashcards') {
            return to_route('practice.flashcards', ['topic' => $found->topic]);
        }

        if ($next['lesson'] !== null) {
            return to_route('learn.lesson', ['lesson' => $next['lesson']['id'], ...($next['lesson']['seconds'] !== null ? ['t' => $next['lesson']['seconds']] : [])]);
        }

        return $next['attempt_id'] !== null ? to_route('practice.attempt', $next['attempt_id']) : to_route('practice.plans.show', ['plan' => $plan, 'misses' => 1]);
    }
}
