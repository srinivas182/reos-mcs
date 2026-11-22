<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Billing\Payouts\PayoutStatements;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Payouts\EarningsController as WebEarnings;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class EarningsController extends Controller
{
    /**
     * Earnings (instructor screen 20, mentor screen 28; B5.2b).
     *
     * Net this month, what goes out in the next weekly payout, each sale's
     * share (newest first) and recent payouts, in cents (USD). Bank and tax
     * details are managed on Stripe from the web; `account` says whether
     * payouts are set up (`ready`, `onboarding`, `needsInfo`, or null).
     * Instructors and mentors only (404 for other roles).
     *
     * @response array{month: string, net_this_month: int, fee_percent: int, pending: int, next_payout: string, account: array{kind: 'express'|'standard', state: 'ready'|'onboarding'|'needsInfo', requirements: int}|null, lines: list<array{id: int, product: string, role: 'seller'|'instructor'|'mentor', amount: int, status: 'pending'|'paid'|'cancelled'|'reversed', date: string|null}>, payouts: list<array{id: string, amount: int, status: 'sent'|'failed'|'sending', date: string|null}>}
     */
    public function __invoke(#[CurrentUser] User $user, ActiveRole $roles, PayoutStatements $statements): JsonResponse
    {
        WebEarnings::payee($user, $roles);

        return response()->json($statements->person($user, CarbonImmutable::now()));
    }
}
