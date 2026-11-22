<?php

declare(strict_types=1);

namespace App\Billing\Payouts;

use App\Billing\Gateways\Gateways;
use App\Billing\GatewaySettings;
use App\Models\ConnectedAccount;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Who can be paid, and their Stripe account (B5.2b). A school or a person
 * sets up payouts on Stripe's own pages (Express); a school may connect its
 * own Stripe account instead when screen 66 allows tenant accounts. Each
 * connection is audited.
 */
final readonly class ConnectedAccounts
{
    public function __construct(
        private PayoutProviders $providers,
        private GatewaySettings $gateways,
        private AuditLogger $audit,
    ) {}

    public function of(Organisation|User $owner, ?string $environment = null): ?ConnectedAccount
    {
        $environment ??= $this->providers->current()?->environment();

        if ($environment === null) {
            return null;
        }

        return ConnectedAccount::query()
            ->where('owner_type', self::type($owner))
            ->where('owner_id', $owner->id)
            ->where('environment', $environment)
            ->first();
    }

    /** The link to Stripe's onboarding (or to finish it), creating the Express account the first time. */
    public function onboard(Organisation|User $owner, ?string $email, User $actor, string $returnUrl): string
    {
        $provider = $this->provider();
        $account = $this->of($owner, $provider->environment());

        if ($account === null) {
            $id = $provider->createAccount($email, $owner instanceof Organisation ? 'company' : 'individual', self::type($owner).':'.$owner->id);
            $account = ConnectedAccount::query()->create([
                'owner_type' => self::type($owner), 'owner_id' => $owner->id, 'environment' => $provider->environment(),
                'kind' => ConnectedAccount::EXPRESS, 'stripe_account_id' => $id,
            ]);
            $this->audit->record('payouts.account_created', 'connected_account', (string) $account->id, ['kind' => ConnectedAccount::EXPRESS], $actor, $owner instanceof Organisation ? $owner->id : null);
        }

        return $provider->onboardingLink($account->stripe_account_id, $returnUrl, $returnUrl);
    }

    /** Whether this school may connect its own Stripe account (screen 66, Stripe "tenant accounts"). */
    public function ownAccountAllowed(): bool
    {
        return $this->gateways->for(Gateways::STRIPE)->tenant_accounts;
    }

    /** Where the school connects its own Stripe account. */
    public function ownAccountUrl(Organisation $school, string $redirectUrl, User $actor): string
    {
        if (! $this->ownAccountAllowed()) {
            throw ValidationException::withMessages(['account' => __('payouts.errors.ownNotAllowed')]);
        }

        $state = Str::random(40);
        Cache::put('payouts.own.'.$state, $school->id, now()->addMinutes(30));
        $url = $this->provider()->ownAccountUrl($state, $redirectUrl);

        if ($url === null) {
            throw ValidationException::withMessages(['account' => __('payouts.errors.unavailable')]);
        }

        $this->audit->record('payouts.own_account_started', 'organisation', (string) $school->id, [], $actor, $school->id);

        return $url;
    }

    /** Back from Stripe: the state proves which school asked; the account replaces any Express account. */
    public function completeOwnAccount(Organisation $school, string $state, string $code, User $actor): ConnectedAccount
    {
        if (Cache::pull('payouts.own.'.$state) !== $school->id) {
            throw ValidationException::withMessages(['account' => __('payouts.errors.expired')]);
        }

        $provider = $this->provider();
        $id = $provider->completeOwnAccount($code);
        $previous = $this->of($school, $provider->environment());

        $account = ConnectedAccount::query()->updateOrCreate(
            ['owner_type' => ConnectedAccount::ORGANISATION, 'owner_id' => $school->id, 'environment' => $provider->environment()],
            ['kind' => ConnectedAccount::STANDARD, 'stripe_account_id' => $id],
        );
        $this->refresh($account);
        $this->audit->record('payouts.own_account_connected', 'connected_account', (string) $account->id, ['from' => $previous?->kind, 'to' => ConnectedAccount::STANDARD], $actor, $school->id);

        return $account;
    }

    /** Asks Stripe where the account stands. */
    public function refresh(ConnectedAccount $account): ConnectedAccount
    {
        $provider = $this->providers->current();

        if ($provider === null || $provider->environment() !== $account->environment) {
            return $account;
        }

        $status = $provider->account($account->stripe_account_id);
        $account->forceFill([
            'details_submitted' => $status->detailsSubmitted,
            'payouts_enabled' => $status->payoutsEnabled,
            'requirements' => $status->requirements,
            'checked_at' => now(),
        ])->save();

        return $account;
    }

    /**
     * For the page: where the account stands, or null when none yet.
     *
     * @return array{kind: string, state: string, requirements: int}|null
     */
    public function summary(Organisation|User $owner): ?array
    {
        $account = $this->of($owner);

        return $account === null ? null : [
            'kind' => $account->kind,
            'state' => $account->state(),
            'requirements' => count($account->requirements ?? []),
        ];
    }

    public static function type(Organisation|User $owner): string
    {
        return $owner instanceof Organisation ? ConnectedAccount::ORGANISATION : ConnectedAccount::USER;
    }

    private function provider(): PayoutProvider
    {
        return $this->providers->current() ?? throw ValidationException::withMessages(['account' => __('payouts.errors.unavailable')]);
    }
}
