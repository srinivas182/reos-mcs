<?php

declare(strict_types=1);

namespace App\Billing\Payouts;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Stripe Connect on the Simulator (outside production; B5.2b): onboarding
 * is a clearly marked REOS page that finishes at once, a tenant's "own"
 * account connects without leaving REOS, and transfers succeed without
 * moving money. Never used in production.
 */
final readonly class SimulatedConnect implements PayoutProvider
{
    private const TTL_DAYS = 90;

    public function environment(): string
    {
        return 'simulator';
    }

    public function createAccount(?string $email, string $businessType, string $reference): string
    {
        return 'sim_acct_'.Str::lower(Str::random(16));
    }

    public function onboardingLink(string $accountId, string $refreshUrl, string $returnUrl): string
    {
        return route('payouts.simulate', ['account' => $accountId, 'return' => $returnUrl]);
    }

    public function ownAccountUrl(string $state, string $redirectUrl): string
    {
        return $redirectUrl.'?'.http_build_query(['state' => $state, 'code' => 'sim_code_'.Str::lower(Str::random(12))]);
    }

    public function completeOwnAccount(string $code): string
    {
        $id = 'sim_acct_'.Str::lower(Str::random(16));
        self::finish($id);

        return $id;
    }

    /** The Simulator's onboarding page: "Finish" makes the account ready for payouts. */
    public static function finish(string $accountId): void
    {
        Cache::put('payouts.simulator.'.$accountId, true, now()->addDays(self::TTL_DAYS));
    }

    public function account(string $accountId): AccountStatus
    {
        $ready = Cache::get('payouts.simulator.'.$accountId) === true;

        return new AccountStatus($ready, $ready, $ready ? [] : ['external_account', 'tos_acceptance.date']);
    }

    public function transfer(string $accountId, int $amountCents, string $currency, string $key, string $description): string
    {
        return 'sim_tr_'.substr(hash('sha256', $key), 0, 20);
    }

    public function reverse(string $transferId, int $amountCents, string $key): void {}

    /** @return array{0: string, 1: string|null}|null */
    public function event(string $body, ?string $signature): ?array
    {
        return null;
    }
}
