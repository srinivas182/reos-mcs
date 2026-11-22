<?php

declare(strict_types=1);

namespace App\Billing;

use App\Billing\Gateways\Gateways;
use App\Billing\Gateways\SimulatedGateway;
use App\Models\Order;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Sales tax for gateways without built-in tax (B5.2a): Stripe's tax
 * calculation API works out the same tax Stripe Tax charges at Stripe, from
 * the state and ZIP code, so every gateway charges the same. After payment
 * the calculation becomes a tax transaction, for filing. Without Stripe keys
 * outside production, the Simulator's demo rate.
 */
final readonly class TaxCalculator
{
    public function __construct(private Gateways $gateways, private Http $http, private Application $app) {}

    /** Sets the order's tax before the gateway is asked for its page. */
    public function calculate(Order $order): void
    {
        $secret = $this->gateways->stripeSecret();

        if ($secret === null) {
            if ($this->app->isProduction()) {
                throw ValidationException::withMessages(['postal_code' => __('billing.errors.tax')]);
            }

            $order->forceFill(['tax_cents' => (int) round($order->chargeCents() * SimulatedGateway::TAX_RATE)])->save();

            return;
        }

        $response = $this->http->withToken($secret)->asForm()->acceptJson()->timeout(20)->post('https://api.stripe.com/v1/tax/calculations', [
            'currency' => $order->currency,
            'line_items' => [['amount' => $order->chargeCents(), 'reference' => $order->product->key, 'tax_behavior' => 'exclusive']],
            'customer_details' => ['address' => ['country' => 'US', 'state' => $order->state_code, 'postal_code' => $order->postal_code], 'address_source' => 'billing'],
        ]);

        $id = $response->json('id');
        $tax = $response->json('tax_amount_exclusive');

        if (! $response->successful() || ! is_string($id) || ! is_int($tax)) {
            throw ValidationException::withMessages(['postal_code' => __('billing.errors.tax')]);
        }

        $order->forceFill(['tax_cents' => $tax, 'tax_calculation_id' => $id])->save();
    }

    /** Records the paid order's tax with Stripe Tax, for filing. */
    public function record(Order $order): void
    {
        $secret = $this->gateways->stripeSecret();

        if ($secret === null || $order->tax_calculation_id === null) {
            return;
        }

        $response = $this->http->withToken($secret)->asForm()->acceptJson()->timeout(20)
            ->withHeaders(['Idempotency-Key' => 'tax:'.$order->uuid])
            ->post('https://api.stripe.com/v1/tax/transactions/create_from_calculation', ['calculation' => $order->tax_calculation_id, 'reference' => $order->uuid]);

        if (! $response->successful()) {
            report(new RuntimeException('Stripe Tax did not record order '.$order->uuid.' (HTTP '.$response->status().').'));
        }
    }
}
