<?php

declare(strict_types=1);

namespace App\Billing\Gateways;

use App\Integrations\Catalogue;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Services\Integrations\IntegrationHub;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;

/**
 * Which gateway takes a payment (B5.1): Stripe, as set in Settings ›
 * Integrations. Test or live keys use Stripe; the Simulator (outside
 * production) takes demo payments; Off pauses purchases. B5.2 adds routing
 * per state and product, more gateways and the kill switch (screen 66).
 */
final readonly class Gateways
{
    public const STRIPE = 'stripe';

    public function __construct(
        private Application $app,
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private CredentialVault $vault,
        private Http $http,
    ) {}

    /** The gateway for checkout now, or null when purchases are paused. */
    public function current(): ?PaymentGateway
    {
        $definition = $this->catalogue->find(self::STRIPE);
        $mode = $definition === null ? IntegrationMode::Off : $this->hub->status($definition)->effectiveMode();

        if ($mode === IntegrationMode::Simulator) {
            return $this->app->isProduction() ? null : new SimulatedGateway;
        }

        $environment = $mode->environment();

        if ($environment === null) {
            return null;
        }

        $keys = $this->vault->get(self::STRIPE, $environment);
        $secret = is_string($keys['secret_key'] ?? null) ? trim($keys['secret_key']) : '';

        if ($secret === '') {
            return null;
        }

        return new StripeGateway($this->http, $secret, is_string($keys['webhook_secret'] ?? null) ? trim($keys['webhook_secret']) : '', $environment->value);
    }

    /** Stripe's publishable key for the page's card fields. */
    public function publishableKey(): ?string
    {
        $definition = $this->catalogue->find(self::STRIPE);
        $environment = ($definition === null ? IntegrationMode::Off : $this->hub->status($definition)->effectiveMode())->environment();

        if ($environment === null) {
            return null;
        }

        $key = $this->vault->get(self::STRIPE, $environment)['publishable_key'] ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }
}
