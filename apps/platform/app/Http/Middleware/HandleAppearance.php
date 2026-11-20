<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Appearance;
use App\Enums\TextSize;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the person's theme and text size with the root view, so `<html>` is
 * rendered with the right `class`, `data-theme` and `data-text-size` on the
 * first paint. A signed-in person's saved choice wins over the device cookie.
 */
final class HandleAppearance
{
    public const APPEARANCE_COOKIE = 'appearance';

    public const TEXT_SIZE_COOKIE = 'text_size';

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

        View::share('appearance', $appearance->value);
        View::share('theme', $appearance->theme());
        View::share('textSize', $textSize->value);

        return $next($request);
    }
}
