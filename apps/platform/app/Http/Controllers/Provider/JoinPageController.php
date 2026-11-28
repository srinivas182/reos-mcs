<?php

declare(strict_types=1);

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\ProviderNetwork\ProviderJoining;
use App\ProviderNetwork\ProviderOnboarding;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/** Join the provider network on the web (B15.1 and B15.2, provider screens 1–6): every step, from welcome to the network rules. */
final class JoinPageController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, ProviderJoining $joining, ProviderOnboarding $onboarding): Response
    {
        $status = $onboarding->status($user);

        return Inertia::render('provider/join', ['categories' => $joining->categories($onboarding->profile($user)->state_code), 'onboarding' => $status]);
    }
}
