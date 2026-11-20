<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Identity\IdentityProvider;
use App\Identity\WebOAuth;
use App\Services\Identity\AccountResolver;
use App\Services\Identity\AppSignInHandoff;
use App\Services\Identity\WebSignIn;
use Database\Seeders\Demo\Persona;
use Database\Seeders\Demo\Personas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** "Continue with Google" and "Continue with Apple" on the web. */
final class SocialSignInController extends Controller
{
    public function redirect(IdentityProvider $provider, Request $request, WebOAuth $oauth, AppSignInHandoff $app): RedirectResponse
    {
        // Started from the phone app (system browser with a PKCE challenge)?
        $app->begin($request);

        if (! $app->pending($request) && $request->user() !== null) {
            return redirect()->route('dashboard');
        }

        return redirect()->away($oauth->start($provider, $request));
    }

    public function callback(Request $request, WebOAuth $oauth, AccountResolver $accounts, WebSignIn $signIn, AppSignInHandoff $app): RedirectResponse
    {
        $provider = str_contains($request->path(), 'apple') ? IdentityProvider::Apple : IdentityProvider::Google;

        // Apple answers with a cross-site POST, which doesn't carry our
        // (SameSite=Lax) session cookie. Hold the answer for five minutes
        // and continue with a same-site GET, which does.
        if ($request->isMethod('post')) {
            $key = Str::random(40);
            Cache::put("reos:oauth-handoff:{$key}", $request->only(['state', 'code', 'user', 'error', 'demo_email']), 300);

            return redirect()->to($request->url().'?'.http_build_query(['handoff' => $key]), 303);
        }

        if ($request->filled('handoff')) {
            $answer = Cache::pull('reos:oauth-handoff:'.$request->string('handoff')->toString());
            $request->merge(is_array($answer) ? $answer : []);
        }
        ['claims' => $claims, 'name' => $name] = $oauth->finish($provider, $request);
        ['user' => $user, 'created' => $created] = $accounts->fromIdentity($claims, $name);

        if ($app->pending($request)) {
            return redirect()->away($app->finish($request, $user, $provider->value, $created));
        }

        return $signIn->complete($request, $user, $provider->value);
    }

    /** Demo sign-in: pick a journey persona (demo data, outside production only). */
    public function demo(IdentityProvider $provider, Request $request): Response
    {
        if (app()->isProduction()) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('auth/demo-sign-in', [
            'provider' => $provider->value,
            'state' => $request->string('state')->toString(),
            'callback' => url("integrations/{$provider->integrationId()}/callback"),
            'personas' => array_map(static fn (Persona $persona): array => [
                'name' => $persona->name,
                'email' => $persona->email,
                'title' => $persona->title,
                'organisation' => $persona->organisation,
            ], Personas::all()),
        ]);
    }
}
