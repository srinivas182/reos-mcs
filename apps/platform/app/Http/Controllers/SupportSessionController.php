<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Tenancy\SupportAccess;
use App\Tenancy\SupportAccessStatus;
use App\Tenancy\Tenancy;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** "End support session" in the banner (A4.1c): back to the REOS back office. */
final class SupportSessionController extends Controller
{
    public function destroy(Request $request, #[CurrentUser] User $user, SupportAccess $support, Tenancy $tenancy): RedirectResponse
    {
        $grant = $support->current($user, $request);

        if ($grant !== null) {
            $tenancy->asPlatform(fn () => $support->end($grant, SupportAccessStatus::Ended, $user));
            $request->session()->forget(SupportAccess::SESSION_KEY);
            $request->session()->regenerate();
            Inertia::flash('toast', ['type' => 'success', 'message' => __('support.endedToast', ['tenant' => $grant->organisation->name])]);

            return to_route('tenants.show', $grant->organisation_id);
        }

        return to_route('dashboard');
    }
}
