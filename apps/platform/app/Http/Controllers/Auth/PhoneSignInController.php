<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PhoneStartRequest;
use App\Services\Identity\PhoneSignIn;
use App\Services\Identity\WebSignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Phone-code sign-in on the web, for people who signed up on the phone with a number. */
final class PhoneSignInController extends Controller
{
    public function start(PhoneStartRequest $request, PhoneSignIn $phoneSignIn): RedirectResponse
    {
        $phoneSignIn->start($request->phone(), app()->getLocale());

        return back()->with('phoneCodeSentTo', $request->phone());
    }

    public function verify(Request $request, PhoneSignIn $phoneSignIn, WebSignIn $signIn): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'code' => ['required', 'string', 'digits:6'],
        ]);

        ['user' => $user] = $phoneSignIn->verify($validated['phone'], $validated['code']);

        return $signIn->complete($request, $user, 'phone');
    }
}
