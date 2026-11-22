<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payouts;

use App\Authorization\Role;
use App\Billing\Payouts\ConnectedAccounts;
use App\Billing\Payouts\PayoutStatements;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Earnings for instructors and mentors on the web (B5.2b; instructor
 * screen 20 and mentor screen 28 on the phone): net this month, the next
 * payout, each share, and "Set up payouts on Stripe" (bank and tax details
 * are managed on Stripe from the web).
 */
final class EarningsController extends Controller
{
    public function index(#[CurrentUser] User $user, ActiveRole $roles, PayoutStatements $statements): Response
    {
        self::payee($user, $roles);

        return Inertia::render('payouts/earnings', ['statement' => $statements->person($user, CarbonImmutable::now())]);
    }

    public function connect(#[CurrentUser] User $user, ActiveRole $roles, ConnectedAccounts $accounts): HttpResponse
    {
        self::payee($user, $roles);

        return Inertia::location($accounts->onboard($user, $user->email, $user, route('earnings.index')));
    }

    /** Instructors and mentors earn; other roles don't have this page. */
    public static function payee(User $user, ActiveRole $roles): void
    {
        abort_unless(in_array($roles->for($user)?->role, [Role::Instructor, Role::Mentor], true), 404);
    }
}
