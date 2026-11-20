<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceResource;
use App\Http\Resources\MeResource;
use App\Identity\SignInFailed;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Identity\AccountDeletion;
use App\Services\Identity\DeviceSessions;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** The signed-in person and their devices. */
final class MeController extends Controller
{
    /** Get the signed-in person. */
    public function show(#[CurrentUser] User $user): MeResource
    {
        return new MeResource($user->load('socialAccounts'));
    }

    /** List the devices signed in to this account. */
    public function devices(#[CurrentUser] User $user, DeviceSessions $sessions): AnonymousResourceCollection
    {
        return DeviceResource::collection($sessions->list($user));
    }

    /**
     * Sign out a device.
     *
     * Use the current device's id to sign out here, or another id to sign
     * out a lost or old phone.
     */
    public function revokeDevice(int $device, #[CurrentUser] User $user, DeviceSessions $sessions): Response
    {
        $token = $user->tokens()->whereKey($device)->first();

        if (! $token instanceof PersonalAccessToken) {
            throw new NotFoundHttpException;
        }

        $current = $user->currentAccessToken();
        $sessions->revoke($user, $token, $current instanceof PersonalAccessToken && $current->id === $token->id ? 'sign_out' : 'revoke');

        return response()->noContent();
    }

    /**
     * Delete my account.
     *
     * Signs out every device now and deletes the account after 30 days;
     * signing in again before then keeps it. Needs a sign-in from the last
     * 15 minutes: otherwise answers 403 with reason
     * `identity.errors.recentSignInRequired`, and the app asks the person to
     * sign in again first.
     */
    public function destroy(#[CurrentUser] User $user, AccountDeletion $deletion): Response
    {
        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || $token->created_at === null || $token->created_at->lt(now()->subMinutes(15))) {
            throw new SignInFailed('identity.errors.recentSignInRequired', 'Deletion needs a recent sign-in', status: 403);
        }

        $deletion->request($user);

        return response()->noContent();
    }

    /** Sign out this device. */
    public function signOut(#[CurrentUser] User $user, DeviceSessions $sessions): Response
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $sessions->revoke($user, $token, 'sign_out');
        }

        return response()->noContent();
    }
}
