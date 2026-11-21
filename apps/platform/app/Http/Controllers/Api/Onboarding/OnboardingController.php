<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Onboarding\OnboardingRequest;
use App\Http\Resources\Onboarding\OnboardingResource;
use App\Models\User;
use App\Services\Onboarding\Onboarding;
use Illuminate\Container\Attributes\CurrentUser;

/** Path, target state and the 60-second setup (mobile journey screens 1, 2 and 8). */
final class OnboardingController extends Controller
{
    /**
     * Save my getting-started choices.
     *
     * Send only what changed. Choosing `become_agent` makes the person a
     * candidate. `complete: true` marks the setup done, so the chooser and
     * setup don't show again.
     */
    public function update(OnboardingRequest $request, #[CurrentUser] User $user, Onboarding $onboarding): OnboardingResource
    {
        return new OnboardingResource($onboarding->save($user, $request->choices()));
    }
}
