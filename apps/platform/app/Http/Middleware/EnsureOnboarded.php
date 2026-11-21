<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\OnboardingPath;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A new candidate answers the three quick questions (plan setup) before the
 * dashboard, as on the phone. Other paths have no setup yet.
 */
final class EnsureOnboarded
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && self::needsSetup($user) && $request->isMethod('GET')) {
            return redirect()->route('get-started.setup');
        }

        return $next($request);
    }

    public static function needsSetup(User $user): bool
    {
        return $user->onboarded_at === null && $user->onboarding_path === OnboardingPath::BecomeAgent->value;
    }
}
