<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IdentitySignInRequest;
use App\Http\Resources\SignInResource;
use App\Identity\IdentityProvider;
use App\Identity\IdentityTokenVerifier;
use App\Services\Identity\AccountResolver;
use App\Services\Identity\DeviceSessions;

/** Sign in on the phone with Google or Apple (one tap, no password). */
final class IdentitySignInController extends Controller
{
    /**
     * Sign in with Google or Apple.
     *
     * Send the ID token the provider's SDK returned. Creates the account on
     * first sign-in, or joins an existing account with the same verified
     * email. Returns a bearer token for this device.
     *
     * @unauthenticated
     */
    public function __invoke(
        IdentityProvider $provider,
        IdentitySignInRequest $request,
        IdentityTokenVerifier $verifier,
        AccountResolver $accounts,
        DeviceSessions $sessions,
    ): SignInResource {
        $claims = $verifier->verify($provider, $request->string('id_token')->toString());
        ['user' => $user, 'created' => $created] = $accounts->fromIdentity($claims, $request->personName());
        $session = $sessions->issue($user, $request->device(), $provider->value, $created);

        return new SignInResource([
            'user' => $user->load('socialAccounts'),
            'token' => $session['token'],
            'new_device' => $session['new_device'],
            'created' => $created,
        ]);
    }
}
