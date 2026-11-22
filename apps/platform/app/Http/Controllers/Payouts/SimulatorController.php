<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payouts;

use App\Authorization\Permission;
use App\Billing\Payouts\PayoutProviders;
use App\Billing\Payouts\SimulatedConnect;
use App\Http\Controllers\Controller;
use App\Models\ConnectedAccount;
use App\Models\User;
use App\Services\Authorization\Memberships;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Simulator's stand-in for Stripe's onboarding pages (never in
 * production): a clearly marked page whose "Finish" makes the account
 * ready for payouts, then returns to REOS.
 */
final class SimulatorController extends Controller
{
    public function show(Request $request, string $account, #[CurrentUser] User $user, PayoutProviders $providers, Memberships $memberships): Response
    {
        abort_unless($providers->current() instanceof SimulatedConnect && self::mine($account, $user, $memberships) !== null, 404);

        return Inertia::render('payouts/simulate', ['account' => $account, 'return' => self::back($request)]);
    }

    public function finish(Request $request, string $account, #[CurrentUser] User $user, PayoutProviders $providers, Memberships $memberships): RedirectResponse
    {
        $connected = self::mine($account, $user, $memberships);
        abort_unless($providers->current() instanceof SimulatedConnect && $connected !== null, 404);
        SimulatedConnect::finish($account);
        $connected->forceFill(['details_submitted' => true, 'payouts_enabled' => true, 'requirements' => [], 'checked_at' => now()])->save();

        return redirect()->to(self::back($request));
    }

    /** The Simulator account, when it's the person's own or their organisation's. */
    private static function mine(string $account, User $user, Memberships $memberships): ?ConnectedAccount
    {
        $connected = ConnectedAccount::query()->where('stripe_account_id', $account)->where('environment', 'simulator')->first();
        // An organisation's account: only its admins.
        $organisations = [];

        foreach ($memberships->of($user) as $membership) {
            if ($membership->role->allows(Permission::OrganisationManage)) {
                $organisations[] = $membership->organisation->id;
            }
        }

        return match ($connected?->owner_type) {
            ConnectedAccount::USER => $connected->owner_id === $user->id ? $connected : null,
            ConnectedAccount::ORGANISATION => in_array($connected->owner_id, $organisations, true) ? $connected : null,
            default => null,
        };
    }

    /** Only back to REOS itself, never somewhere else. */
    private static function back(Request $request): string
    {
        $return = $request->string('return')->toString();

        return str_starts_with($return, url('/').'/') ? $return : route('dashboard');
    }
}
