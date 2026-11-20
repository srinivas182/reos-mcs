<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Http\Resources\DeviceResource;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Identity\DeviceSessions;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request, #[CurrentUser] User $user, DeviceSessions $sessions): Response
    {
        $props = [
            /* @chisel-2fa */
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            /* @end-chisel-2fa */
            /* @chisel-passkeys */
            'canManagePasskeys' => Features::canManagePasskeys(),
            'passkeys' => Features::canManagePasskeys()
                ? $user
                    ->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn ($passkey) => [
                        'id' => $passkey->id,
                        'name' => $passkey->name,
                        'authenticator' => $passkey->authenticator,
                        'created_at_diff' => $passkey->created_at?->diffForHumans(),
                        'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
                    ])
                    ->values()
                    ->all()
                : [],
            /* @end-chisel-passkeys */
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            // People who signed up with a phone, Google or Apple can add a password.
            'hasPassword' => $user->password !== null,
            'devices' => DeviceResource::collection($sessions->list($user))->resolve($request),
            'browsers' => DB::table('known_devices')
                ->where('user_id', $user->id)
                ->where('platform', 'web')
                ->orderByDesc('last_seen_at')
                ->limit(10)
                ->get(['name', 'last_seen_at'])
                ->map(static fn (object $row): array => ['name' => $row->name, 'last_seen_at' => $row->last_seen_at])
                ->all(),
        ];

        /* @chisel-2fa */
        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $user->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }
        /* @end-chisel-2fa */

        return Inertia::render('settings/security', $props);
    }

    /**
     * Sign out one of the person's phones or tablets.
     */
    public function destroyDevice(int $device, #[CurrentUser] User $user, DeviceSessions $sessions): RedirectResponse
    {
        $token = $user->tokens()->whereKey($device)->first();

        if (! $token instanceof PersonalAccessToken) {
            throw new NotFoundHttpException;
        }

        $sessions->revoke($user, $token, 'revoke');
        Inertia::flash('toast', ['type' => 'success', 'message' => __('identity.devices.signedOut', ['device' => $token->name])]);

        return back();
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $user->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('settings.security.passwordSaved')]);

        return back();
    }
}
