<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PushDeviceRequest;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Notifications\Centre\Push\PushDevices;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class PushDeviceController extends Controller
{
    /**
     * Turn on push for this phone (B6.2b).
     *
     * Send the Expo push token after the person allows notifications. The
     * token belongs to this sign-in: signing out removes it. Sending it
     * again (each app start) is safe.
     *
     * @status 201
     *
     * @response array{phones: int}
     */
    public function store(PushDeviceRequest $request, #[CurrentUser] User $user, PushDevices $devices): JsonResponse
    {
        $signIn = $user->currentAccessToken();
        $name = $request->input('name');

        $devices->register(
            $user,
            $request->string('token')->toString(),
            $request->string('platform')->toString(),
            is_string($name) ? $name : null,
            $signIn instanceof PersonalAccessToken ? $signIn : null,
        );

        return response()->json(['phones' => $devices->count($user)], 201);
    }

    /**
     * Turn off push for this phone (B6.2b).
     *
     * Removes the push token sent with this sign-in.
     */
    public function destroy(#[CurrentUser] User $user, PushDevices $devices): Response
    {
        $signIn = $user->currentAccessToken();

        if ($signIn instanceof PersonalAccessToken) {
            $devices->forgetSignIn($user, $signIn);
        }

        return response()->noContent();
    }
}
