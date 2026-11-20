<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Listeners\RecordWebSignIn;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Fortify;

/**
 * Finishes a web sign-in that didn't go through Fortify's own login form
 * (Google, Apple, phone code). People with two-step turned on still answer
 * its challenge, exactly as after a password.
 */
final class WebSignIn
{
    public function complete(Request $request, User $user, string $method): RedirectResponse
    {
        if ($user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null) {
            // Fortify's two-step challenge reads these and finishes the sign-in.
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => true]);

            return redirect()->route('two-factor.login');
        }

        $request->attributes->set(RecordWebSignIn::METHOD_ATTRIBUTE, $method);
        Auth::guard('web')->login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(Fortify::redirects('login'));
    }
}
