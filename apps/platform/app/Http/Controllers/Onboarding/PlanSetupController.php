<?php

declare(strict_types=1);

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Onboarding\OnboardingRequest;
use App\Http\Resources\Onboarding\OnboardingResource;
use App\Models\State;
use App\Models\User;
use App\Services\Onboarding\Onboarding;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Your plan · 3 quick questions" and "Your plan is ready" on the website
 * (candidate journey screens 8–9), after sign-up.
 */
final class PlanSetupController extends Controller
{
    public function show(Request $request, #[CurrentUser] User $user): Response
    {
        return Inertia::render('get-started/setup', [
            'choices' => [...OnboardingResource::make($user)->resolve($request), 'locale' => $user->locale->value],
        ]);
    }

    public function store(OnboardingRequest $request, #[CurrentUser] User $user, Onboarding $onboarding): RedirectResponse
    {
        $onboarding->save($user, [...$request->choices(), 'complete' => true]);

        return to_route('get-started.plan');
    }

    public function plan(Request $request, #[CurrentUser] User $user): Response|RedirectResponse
    {
        if ($user->onboarded_at === null) {
            return to_route('get-started.setup');
        }

        return Inertia::render('get-started/plan', [
            'onboarding' => OnboardingResource::make($user)->resolve($request),
            'state' => $user->target_state === null ? null : State::query()->find($user->target_state)?->name,
        ]);
    }
}
