<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Appearance;
use App\Enums\Locale;
use App\Enums\TextSize;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the person's theme, text size and language before anything renders,
 * so `<html>` has the right `lang`, `class`, `data-theme` and `data-text-size`
 * on the first paint, and emails and validation use their language.
 *
 * Order: the signed-in person's saved choice, then the device cookie, then
 * (language only) the browser's Accept-Language, then English.
 */
final class HandleAppearance
{
    public const APPEARANCE_COOKIE = 'appearance';

    public const TEXT_SIZE_COOKIE = 'text_size';

    public const LOCALE_COOKIE = 'locale';

    /** One year. */
    public const COOKIE_MINUTES = 60 * 24 * 365;

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $appearance = $user instanceof User ? $user->appearance : Appearance::fromCookie($request->cookie(self::APPEARANCE_COOKIE));
        $textSize = $user instanceof User ? $user->text_size : TextSize::fromCookie($request->cookie(self::TEXT_SIZE_COOKIE));

        $locale = $user instanceof User
            ? $user->locale
            : (Locale::fromTag(self::cookie($request, self::LOCALE_COOKIE))
                ?? Locale::fromTag($request->getPreferredLanguage(Locale::values()))
                ?? Locale::English);

        app()->setLocale($locale->value);

        View::share('appearance', $appearance->value);
        View::share('theme', $appearance->theme());
        View::share('textSize', $textSize->value);

        return $next($request);
    }

    private static function cookie(Request $request, string $name): ?string
    {
        $value = $request->cookie($name);

        return is_string($value) ? $value : null;
    }
}
