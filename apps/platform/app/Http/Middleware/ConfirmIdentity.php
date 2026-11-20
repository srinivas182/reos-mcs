<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Listeners\RecordWebSignIn;
use App\Models\User;
use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sensitive pages (Security, Integrations) ask people to prove it's them:
 * people with a password confirm it (Laravel's RequirePassword); people who
 * signed up with a phone, Google or Apple must have signed in within the
 * last 15 minutes, or sign in again.
 */
final readonly class ConfirmIdentity
{
    public const RECENT_MINUTES = 15;

    public function __construct(private RequirePassword $requirePassword) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->password !== null) {
            /** @var Response */
            return $this->requirePassword->handle($request, $next);
        }

        $signedInAt = $request->session()->get(RecordWebSignIn::SIGNED_IN_AT);

        if (is_int($signedInAt) && $signedInAt >= now()->subMinutes(self::RECENT_MINUTES)->getTimestamp()) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('url.intended', $request->fullUrl());

        return redirect()->route('login')->with('status', __('identity.confirm.signInAgain'));
    }
}
