<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Locale;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers the phone app in the language it is showing, so tool descriptions,
 * notices and validation messages match the screen.
 *
 * Order: the app's Accept-Language (it always sends the language on screen),
 * then the signed-in person's saved language, then English.
 */
final class SetApiLocale
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        $asked = $request->headers->has('Accept-Language') ? $request->getPreferredLanguage(Locale::values()) : null;

        $locale = Locale::fromTag($asked)
            ?? ($user instanceof User ? $user->locale : null)
            ?? Locale::English;

        app()->setLocale($locale->value);

        return $next($request);
    }
}
