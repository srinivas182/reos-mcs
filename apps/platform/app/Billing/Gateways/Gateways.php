<?php

declare(strict_types=1);

namespace App\Billing\Gateways;

use App\Integrations\Catalogue;
use App\Integrations\Credentials\CredentialVault;
use App\Integrations\IntegrationMode;
use App\Models\GatewaySetting;
use App\Models\Price;
use App\Services\Integrations\IntegrationHub;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;

/**
 * The payment gateways (B5.1, B5.2a; screen 66). Each runs as set in
 * Settings › Integrations: test or live keys use the gateway; the Simulator
 * (outside production) takes demo payments; Off means it isn't used.
 * `route()` picks the gateway for a price from screen 66's settings: the
 * states and kinds of product it serves, the default first, skipping any
 * that are paused (the kill switch) or can't take plans; none left means
 * purchases are paused.
 */
final readonly class Gateways
{
    public const STRIPE = 'stripe';

    public const PAYPAL = 'paypal';

    public const AUTHORIZE_NET = 'authorize-net';

    /** Gateways that take payments, in screen 66's order. */
    public const CHECKOUT = [self::STRIPE, self::PAYPAL, self::AUTHORIZE_NET];

    public function __construct(
        private Application $app,
        private Catalogue $catalogue,
        private IntegrationHub $hub,
        private CredentialVault $vault,
        private Http $http,
    ) {}

    /** Stripe, for the customer portal and its publishable key. */
    public function current(): ?PaymentGateway
    {
        return $this->driver(self::STRIPE);
    }

    public function mode(string $gateway): IntegrationMode
    {
        $definition = $this->catalogue->find($gateway);

        return $definition === null ? IntegrationMode::Off : $this->hub->status($definition)->effectiveMode();
    }

    /** The gateway as it runs now, or null when it's off, has no keys, or is the Simulator in production. */
    public function driver(string $gateway): ?PaymentGateway
    {
        $mode = $this->mode($gateway);

        if ($mode === IntegrationMode::Simulator) {
            return $this->app->isProduction() ? null : new SimulatedGateway($gateway);
        }

        $environment = $mode->environment();

        if ($environment === null) {
            return null;
        }

        $keys = $this->vault->get($gateway, $environment);
        $value = static fn (string $key): string => is_string($keys[$key] ?? null) ? trim($keys[$key]) : '';

        return match ($gateway) {
            self::STRIPE => $value('secret_key') === '' ? null : new StripeGateway($this->http, $value('secret_key'), $value('webhook_secret'), $environment->value),
            self::PAYPAL => $value('client_id') === '' || $value('client_secret') === '' ? null : new PayPalGateway($this->http, $value('client_id'), $value('client_secret'), $value('webhook_id'), $environment->value),
            self::AUTHORIZE_NET => $value('api_login_id') === '' || $value('transaction_key') === '' ? null : new AuthorizeNetGateway($this->http, $value('api_login_id'), $value('transaction_key'), $value('signature_key'), $environment->value),
            default => null,
        };
    }

    /** The gateway for this price, or null when purchases are paused for it. */
    public function route(Price $price): ?PaymentGateway
    {
        $settings = GatewaySetting::query()->get()->keyBy('gateway');
        $kind = $price->product->kind->value;
        $hasDefault = $settings->contains(static fn (GatewaySetting $s): bool => $s->is_default);
        $candidates = [];

        foreach (self::CHECKOUT as $index => $id) {
            $setting = $settings->get($id);

            // A gateway nobody set up on screen 66 yet: only Stripe, for every
            // state and product, and the default until another one is.
            if (! $setting instanceof GatewaySetting) {
                if ($id === self::STRIPE) {
                    $candidates[] = [$hasDefault ? 1 : 0, $index, $id];
                }

                continue;
            }

            if ($setting->paused
                || ($setting->states !== null && ! in_array($price->state_code, $setting->states, true))
                || ($setting->used_for !== null && ! in_array($kind, $setting->used_for, true))) {
                continue;
            }

            $candidates[] = [$setting->is_default ? 0 : 1, $setting->position * 10 + $index, $id];
        }

        sort($candidates);

        foreach ($candidates as [, , $id]) {
            $driver = $this->driver($id);

            if ($driver !== null && (! $price->interval->recurring() || $driver->supportsRecurring())) {
                return $driver;
            }
        }

        return null;
    }

    /** Stripe's secret key, for tax calculations at other gateways. */
    public function stripeSecret(): ?string
    {
        $environment = $this->mode(self::STRIPE)->environment();
        $key = $environment === null ? null : ($this->vault->get(self::STRIPE, $environment)['secret_key'] ?? null);

        return is_string($key) && $key !== '' ? $key : null;
    }

    /** Stripe's publishable key for the page's card fields. */
    public function publishableKey(): ?string
    {
        $environment = $this->mode(self::STRIPE)->environment();

        if ($environment === null) {
            return null;
        }

        $key = $this->vault->get(self::STRIPE, $environment)['publishable_key'] ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }
}
