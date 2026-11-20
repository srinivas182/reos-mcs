<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Listeners\RecordWebSignIn;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out browsers that were already signed in when the person asked to
 * delete their account. Signing in again (which cancels the deletion) works.
 */
final class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->deletion_requested_at !== null && $request->hasSession()) {
            $signedInAt = $request->session()->get(RecordWebSignIn::SIGNED_IN_AT);

            if (! is_int($signedInAt) || $signedInAt < $user->deletion_requested_at->getTimestamp()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login');
            }
        }

        return $next($request);
    }
}
