<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payouts;

use App\Authorization\Role;
use App\Billing\Payouts\ConnectedAccounts;
use App\Billing\Payouts\PayoutStatements;
use App\Enums\OrganisationType;
use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sales and payouts (school admin web screen 53, B5.2b): the school's
 * products with prices and sponsor discounts, this month's payout
 * breakdown with fees shown, its Stripe account (set up payouts on
 * Stripe, or connect its own Stripe account), recent payouts, and Export.
 */
final class SalesController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user, ActiveRole $roles, PayoutStatements $statements): Response
    {
        return Inertia::render('payouts/sales', [
            'school' => ['name' => $this->school($user, $roles)->name],
            'statement' => $statements->school($this->school($user, $roles), self::month($request)),
        ]);
    }

    public function export(Request $request, #[CurrentUser] User $user, ActiveRole $roles, PayoutStatements $statements): StreamedResponse
    {
        $school = $this->school($user, $roles);
        $month = self::month($request);
        $rows = $statements->schoolExport($school, $month);

        return response()->streamDownload(static function () use ($rows): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, [__('payouts.export.date'), __('payouts.export.order'), __('payouts.export.product'), __('payouts.export.state'), __('payouts.export.price'), __('payouts.export.discount'), __('payouts.export.fee'), __('payouts.export.payees'), __('payouts.export.net'), __('payouts.export.orderStatus'), __('payouts.export.payoutStatus')], escape: '');

            foreach ($rows as $row) {
                fputcsv($out, $row, escape: '');
            }

            fclose($out);
        }, 'reos-sales-'.$month->format('Y-m').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** "Set up payouts": Stripe's own onboarding (bank and tax details stay with Stripe). */
    public function connect(#[CurrentUser] User $user, ActiveRole $roles, ConnectedAccounts $accounts): HttpResponse
    {
        $school = $this->school($user, $roles);

        return Inertia::location($accounts->onboard($school, $user->email, $user, route('sales.index')));
    }

    /** "Connect your own Stripe" (when screen 66 allows tenant accounts). */
    public function connectOwn(#[CurrentUser] User $user, ActiveRole $roles, ConnectedAccounts $accounts): HttpResponse
    {
        return Inertia::location($accounts->ownAccountUrl($this->school($user, $roles), route('sales.own-account'), $user));
    }

    public function ownAccount(Request $request, #[CurrentUser] User $user, ActiveRole $roles, ConnectedAccounts $accounts): RedirectResponse
    {
        $input = $request->validate(['state' => ['required', 'string', 'max:64'], 'code' => ['required', 'string', 'max:255']]);
        $accounts->completeOwnAccount($this->school($user, $roles), (string) $input['state'], (string) $input['code'], $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('payouts.ownConnected')]);

        return to_route('sales.index');
    }

    private function school(User $user, ActiveRole $roles): Organisation
    {
        $membership = $roles->for($user);
        abort_unless($membership !== null && $membership->role === Role::SchoolAdmin && $membership->organisation->type === OrganisationType::School, 404);

        return $membership->organisation;
    }

    private static function month(Request $request): CarbonImmutable
    {
        $month = $request->validate(['month' => ['sometimes', 'date_format:Y-m']])['month'] ?? null;

        return is_string($month) ? CarbonImmutable::createFromFormat('Y-m-d', $month.'-01') ?: CarbonImmutable::now() : CarbonImmutable::now();
    }
}
