<?php

use App\Authorization\RoleRefused;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogSupportViews;
use App\Http\Middleware\PlatformScope;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SetApiLocale;
use App\Http\Middleware\TraceRequests;
use App\Identity\SignInFailed;
use App\Services\Identity\AppSignInHandoff;
use App\Sso\SsoProblem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: static function (): void {
            // Vendors sign their webhooks; they run in platform scope (ADR 0015).
            Route::middleware(['api', 'tenancy.platform'])->group(base_path('routes/webhooks.php'));
            // Sign-in answers posted from other sites: no session (see the file).
            Route::group([], base_path('routes/handoff.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Monitoring settings from Settings › Integrations, and request traces.
        $middleware->prepend(TraceRequests::class);

        // Sign-in callbacks: Apple posts from its own site; the one-time OAuth
        // `state` protects them instead of a CSRF token.
        $middleware->preventRequestForgery(except: [
            'integrations/apple-sign-in/callback',
            'integrations/google-sign-in/callback',
            // Connections (B3.1): some tools post the sign-in answer back.
            'integrations/*/callback',
        ]);

        $middleware->encryptCookies(except: ['appearance', 'text_size', 'locale', 'sidebar_state', 'reos_tz']);

        // The phone app's language (B3.2b): tool descriptions and messages match its screen.
        // Tenancy (A4.1, ADR 0015): whose data this request may touch.
        $middleware->api(append: [SetApiLocale::class, ResolveTenant::class]);
        $middleware->alias(['tenancy.platform' => PlatformScope::class]);
        // Before throttling (the API limit is per tenant) and route model binding (bound models are isolated).
        $middleware->throttleApi();
        // ThrottleRequests comes before SubstituteBindings in Laravel's priority list, so this covers both.
        $middleware->prependToPriorityList(before: ThrottleRequests::class, prepend: ResolveTenant::class);

        $middleware->web(append: [
            EnsureAccountActive::class,
            HandleAppearance::class,
            ResolveTenant::class,
            LogSupportViews::class,
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

            // Started from the phone app: back to the app, which shows the reason.
            $handoff = app(AppSignInHandoff::class);

            if ($handoff->pending($request)) {
                return redirect()->away($handoff->fail($request, $e->reason));
            }

            // On the web, show it on the sign-in page (codes next to the code box).
            $field = in_array($e->reason, ['identity.errors.wrongCode', 'identity.errors.locked'], true) ? 'code' : 'sign_in';

            return redirect()->route('login')->withInput($request->only('phone'))->withErrors([$field => $message]);
        });

        // Single sign-on problems (A4.2c): a plain message on the sign-in page.
        $exceptions->dontReport(SsoProblem::class);
        $exceptions->render(function (SsoProblem $e, Request $request) {
            $message = __($e->reason);

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'reason' => $e->reason], $e->status);
            }

            return redirect()->route('login')->withErrors(['sign_in' => $message]);
        });

        // Role problems (A3.2): a reason key the apps translate, or back with an error.
        $exceptions->dontReport(RoleRefused::class);
        $exceptions->render(function (RoleRefused $e, Request $request) {
            $message = __($e->reason);

            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'reason' => $e->reason], $e->status);
            }

            return back()->withErrors(['role' => $message]);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
