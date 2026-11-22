<?php

declare(strict_types=1);

namespace App\Billing\Payouts;

/**
 * Pays schools, instructors and mentors (B5.2b): Stripe Connect, or the
 * Simulator outside production. Accounts are onboarded on the provider's
 * own pages, so bank and tax details never touch REOS.
 */
interface PayoutProvider
{
    /** `live`, `sandbox` or `simulator`. */
    public function environment(): string;

    /** Creates an Express account; returns its id. */
    public function createAccount(?string $email, string $businessType, string $reference): string;

    /** A one-time link to the provider's onboarding (or account update) pages. */
    public function onboardingLink(string $accountId, string $refreshUrl, string $returnUrl): string;

    /** Where a tenant connects its own Stripe account (OAuth), or null when that isn't set up. */
    public function ownAccountUrl(string $state, string $redirectUrl): ?string;

    /** Finishes connecting a tenant's own account; returns its id. */
    public function completeOwnAccount(string $code): string;

    public function account(string $accountId): AccountStatus;

    /** Sends money to the account once per key; returns the transfer id. */
    public function transfer(string $accountId, int $amountCents, string $currency, string $key, string $description): string;

    /** Takes back part or all of a transfer (a refund after payout). */
    public function reverse(string $transferId, int $amountCents, string $key): void;

    /**
     * A signed webhook event; null when the signature is wrong.
     *
     * @return array{0: string, 1: string|null}|null [event id, account id]
     */
    public function event(string $body, ?string $signature): ?array;
}
