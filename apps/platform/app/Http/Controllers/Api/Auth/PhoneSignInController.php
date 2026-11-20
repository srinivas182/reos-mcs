<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PhoneStartRequest;
use App\Http\Requests\Api\PhoneVerifyRequest;
use App\Http\Resources\SignInResource;
use App\Services\Identity\DeviceSessions;
use App\Services\Identity\PhoneSignIn;
use Illuminate\Http\Response;

/** Sign in on the phone with a six-digit code sent by text message. */
final class PhoneSignInController extends Controller
{
    /**
     * Send a sign-in code.
     *
     * Texts a six-digit code to the number, valid for 10 minutes. Limited per
     * number and per network to stop abuse.
     *
     * @unauthenticated
     */
    public function start(PhoneStartRequest $request, PhoneSignIn $phoneSignIn): Response
    {
        $phoneSignIn->start($request->phone(), app()->getLocale());

        return response()->noContent(202);
    }

    /**
     * Check the code and sign in.
     *
     * Creates the account on first sign-in. Five wrong codes lock the number
     * for 15 minutes.
     *
     * @unauthenticated
     */
    public function verify(PhoneVerifyRequest $request, PhoneSignIn $phoneSignIn, DeviceSessions $sessions): SignInResource
    {
        ['user' => $user, 'created' => $created] = $phoneSignIn->verify($request->phone(), $request->string('code')->toString());
        $session = $sessions->issue($user, $request->device(), 'phone', $created);

        return new SignInResource([
            'user' => $user->load('socialAccounts'),
            'token' => $session['token'],
            'new_device' => $session['new_device'],
            'created' => $created,
        ]);
    }
}
