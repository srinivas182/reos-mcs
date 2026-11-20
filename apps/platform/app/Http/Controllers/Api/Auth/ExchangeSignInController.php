<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ExchangeSignInRequest;
use App\Http\Resources\SignInResource;
use App\Services\Identity\AppSignInHandoff;
use App\Services\Identity\DeviceSessions;

/** Finishes a browser sign-in (Google, or Apple on Android) the app started. */
final class ExchangeSignInController extends Controller
{
    /**
     * Swap a browser sign-in code for a device token.
     *
     * The app opens `/auth/{google|apple}/redirect?app_challenge=…` in the
     * system browser. When the person has signed in, the browser returns to
     * `reos://auth?code=…`. Send that code with the PKCE verifier. Codes work
     * once, for two minutes.
     *
     * @unauthenticated
     */
    public function __invoke(ExchangeSignInRequest $request, AppSignInHandoff $handoff, DeviceSessions $sessions): SignInResource
    {
        ['user' => $user, 'method' => $method, 'created' => $created] = $handoff->redeem(
            $request->string('code')->toString(),
            $request->string('code_verifier')->toString(),
        );
        $session = $sessions->issue($user, $request->device(), $method, $created);

        return new SignInResource([
            'user' => $user->load('socialAccounts'),
            'token' => $session['token'],
            'new_device' => $session['new_device'],
            'created' => $created,
        ]);
    }
}
