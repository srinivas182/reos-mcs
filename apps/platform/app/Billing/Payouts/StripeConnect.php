<?php

declare(strict_types=1);

namespace App\Billing\Payouts;

use App\Billing\Gateways\StripeGateway;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;

/**
 * Stripe Connect (B5.2b): Express accounts onboarded with account links,
 * a tenant's own Stripe account through Connect OAuth, transfers (separate
 * charges and transfers, so any gateway's sales can be paid out from the
 * platform balance) and reversals, and `account.updated` webhooks.
 */
final readonly class StripeConnect implements PayoutProvider
{
    public function __construct(
        private Http $http,
        private string $secretKey,
        private string $clientId,
        private string $webhookSecret,
        private string $environment,
    ) {}

    public function environment(): string
    {
        return $this->environment;
    }

    public function createAccount(?string $email, string $businessType, string $reference): string
    {
        $account = $this->post('/accounts', array_filter([
            'type' => 'express',
            'country' => 'US',
            'email' => $email,
            'business_type' => $businessType,
            'capabilities' => ['transfers' => ['requested' => 'true']],
            'metadata' => ['reos_owner' => $reference],
        ], static fn (mixed $v): bool => $v !== null), 'account:'.$reference.':'.$this->environment);

        $id = $account['id'] ?? null;

        if (! is_string($id)) {
            throw new RuntimeException('Stripe created no account.');
        }

        return $id;
    }

    public function onboardingLink(string $accountId, string $refreshUrl, string $returnUrl): string
    {
        $link = $this->post('/account_links', [
            'account' => $accountId,
            'refresh_url' => $refreshUrl,
            'return_url' => $returnUrl,
            'type' => 'account_onboarding',
        ]);
        $url = $link['url'] ?? null;

        if (! is_string($url)) {
            throw new RuntimeException('Stripe gave no onboarding link.');
        }

        return $url;
    }

    public function ownAccountUrl(string $state, string $redirectUrl): ?string
    {
        if ($this->clientId === '') {
            return null;
        }

        return 'https://connect.stripe.com/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'scope' => 'read_write',
            'state' => $state,
            'redirect_uri' => $redirectUrl,
        ]);
    }

    public function completeOwnAccount(string $code): string
    {
        $response = $this->http->asForm()->acceptJson()->timeout(20)
            ->post('https://connect.stripe.com/oauth/token', ['client_secret' => $this->secretKey, 'grant_type' => 'authorization_code', 'code' => $code]);
        $id = $response->json('stripe_user_id');

        if (! $response->successful() || ! is_string($id)) {
            throw new RuntimeException('Stripe did not connect the account (HTTP '.$response->status().').');
        }

        return $id;
    }

    public function account(string $accountId): AccountStatus
    {
        $response = $this->api()->get('/accounts/'.rawurlencode($accountId));

        if (! $response->successful()) {
            throw new RuntimeException('Stripe did not return the account (HTTP '.$response->status().').');
        }

        $due = $response->json('requirements.currently_due');

        return new AccountStatus(
            detailsSubmitted: $response->json('details_submitted') === true,
            payoutsEnabled: $response->json('payouts_enabled') === true,
            requirements: is_array($due) ? array_values(array_filter($due, is_string(...))) : [],
        );
    }

    public function transfer(string $accountId, int $amountCents, string $currency, string $key, string $description): string
    {
        $transfer = $this->post('/transfers', [
            'amount' => $amountCents,
            'currency' => $currency,
            'destination' => $accountId,
            'description' => $description,
            'metadata' => ['reos_payout' => $key],
        ], 'payout:'.$key);
        $id = $transfer['id'] ?? null;

        if (! is_string($id)) {
            throw new RuntimeException('Stripe made no transfer.');
        }

        return $id;
    }

    public function reverse(string $transferId, int $amountCents, string $key): void
    {
        $this->post('/transfers/'.rawurlencode($transferId).'/reversals', ['amount' => $amountCents], 'reversal:'.$key);
    }

    /** @return array{0: string, 1: string|null}|null */
    public function event(string $body, ?string $signature): ?array
    {
        if ($this->webhookSecret === '' || $signature === null || ! StripeGateway::verify($body, $signature, $this->webhookSecret, time())) {
            return null;
        }

        $event = json_decode($body, true);

        if (! is_array($event) || ! is_string($event['id'] ?? null)) {
            return null;
        }

        $account = $event['account'] ?? null;

        // `account.updated` (and anything else about one account) refreshes that account.
        return [$event['id'], is_string($account) ? $account : null];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<mixed>
     */
    private function post(string $path, array $params, ?string $idempotencyKey = null): array
    {
        $request = $this->api()->asForm();

        if ($idempotencyKey !== null) {
            $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        $response = $request->post($path, $params);

        if (! $response->successful()) {
            $message = $response->json('error.message');

            throw new RuntimeException('Stripe refused the request: '.(is_string($message) ? $message : 'HTTP '.$response->status()));
        }

        return (array) $response->json();
    }

    private function api(): PendingRequest
    {
        return $this->http->baseUrl(StripeGateway::API)->withToken($this->secretKey)->acceptJson()->timeout(20)->retry(2, 300, throw: false);
    }
}
