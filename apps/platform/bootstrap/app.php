<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\TraceRequests;
use App\Identity\SignInFailed;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Monitoring settings from Settings › Integrations, and request traces.
        $middleware->prepend(TraceRequests::class);

        // Sign-in callbacks: Apple posts from its own site; the one-time OAuth
        // `state` protects them instead of a CSRF token.
        $middleware->preventRequestForgery(except: [
            'integrations/apple-sign-in/callback',
            'integrations/google-sign-in/callback',
        ]);

        $middleware->encryptCookies(except: ['appearance', 'text_size', 'locale', 'sidebar_state']);

        $middleware->web(append: [
            EnsureAccountActive::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Reports to Sentry only when it is switched on in Settings › Integrations.
        Integration::handles($exceptions);

        // Sign-in problems answer in the person's language, never with internals.
        $exceptions->dontReport(SignInFailed::class);
        $exceptions->render(function (SignInFailed $e, Request $request) {
            $message = __($e->reason, $e->parameters);

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'reason' => $e->reason], $e->status);
            }

            // On the web, show it on the sign-in page (codes next to the code box).
            $field = in_array($e->reason, ['identity.errors.wrongCode', 'identity.errors.locked'], true) ? 'code' : 'sign_in';

            return redirect()->route('login')->withInput($request->only('phone'))->withErrors([$field => $message]);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
