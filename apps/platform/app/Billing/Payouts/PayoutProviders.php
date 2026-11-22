<?php

declare(strict_types=1);

namespace App\Billing\Payouts;

use App\Billing\Gateways\Gateways;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;

/**
 * Stripe Connect as set in Settings › Integrations (B5.2b): test or live
 * keys pay out through Stripe; the Simulator (outside production) pretends;
 * Off means payouts wait (earnings keep adding up).
 */
final readonly class PayoutProviders
{
    public const ID = 'stripe-connect';

    public function __construct(private Gateways $gateways, private CredentialVault $vault, private Http $http, private Application $app) {}

    public function current(): ?PayoutProvider
    {
        $mode = $this->gateways->mode(self::ID);

        if ($mode === IntegrationMode::Simulator) {
            return $this->app->isProduction() ? null : new SimulatedConnect;
        }

        $environment = $mode->environment();

        if ($environment === null) {
            return null;
        }

        $keys = $this->vault->get(self::ID, $environment);
        $value = static fn (string $key): string => is_string($keys[$key] ?? null) ? trim($keys[$key]) : '';

        return $value('secret_key') === '' ? null : new StripeConnect($this->http, $value('secret_key'), $value('client_id'), $value('webhook_secret'), $environment->value);
    }
}
