<?php

declare(strict_types=1);

namespace App\Billing\Gateways;

use App\Models\GatewayCustomer;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * Stripe (B5.1): embedded Checkout (Stripe's own card fields inside the REOS
 * page, PCI SAQ A) with Stripe Tax, Billing for subscriptions, the customer
 * portal, and signed webhooks (`Stripe-Signature`, five-minute tolerance).
 */
final readonly class StripeGateway implements PaymentGateway
{
    public const API = 'https://api.stripe.com/v1';

    private const TOLERANCE_SECONDS = 300;

    public function __construct(private Http $http, private string $secretKey, private string $webhookSecret, private string $environment) {}

    public function id(): string
    {
        return 'stripe';
    }

    public function environment(): string
    {
        return $this->environment;
    }

    public function supportsRecurring(): bool
    {
        return true;
    }

    public function calculatesTax(): bool
    {
        return true;
    }

    public function checkout(Order $order, User $buyer, string $returnUrl): CheckoutSession
    {
        $price = $order->price;
        $product = $order->product;
        $recurring = $price->interval->recurring();
        $name = $product->name.($order->sponsor !== null ? ' ('.$order->sponsor->name.')' : '');

        $params = [
            'ui_mode' => 'embedded',
            'mode' => $recurring ? 'subscription' : 'payment',
            'return_url' => $returnUrl,
            'customer' => $this->customer($buyer),
            'customer_update' => ['address' => 'auto', 'name' => 'auto'],
            'client_reference_id' => $order->uuid,
            'metadata' => ['order' => $order->uuid, 'state' => $order->state_code],
            'automatic_tax' => ['enabled' => 'true'],
            'billing_address_collection' => 'required',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => array_filter([
                    'currency' => $order->currency,
                    'unit_amount' => $order->chargeCents(),
                    'tax_behavior' => 'exclusive',
                    'product_data' => ['name' => $name, 'metadata' => ['product' => $product->key]],
                    'recurring' => $recurring ? ['interval' => $price->interval->value] : null,
                ]),
            ]],
        ];

        if ($recurring) {
            $params['subscription_data'] = ['metadata' => ['order' => $order->uuid]];
        } else {
            $params['payment_intent_data'] = ['metadata' => ['order' => $order->uuid]];
            $params['invoice_creation'] = ['enabled' => 'true'];
        }

        $session = $this->post('/checkout/sessions', $params, 'checkout:'.$order->uuid);
        $id = $session['id'] ?? null;

        if (! is_string($id)) {
            throw new RuntimeException('Stripe started no checkout.');
        }

        return new CheckoutSession($id, is_string($session['client_secret'] ?? null) ? $session['client_secret'] : null);
    }

    public function result(string $sessionId): PaymentResult
    {
        $response = $this->api()->get('/checkout/sessions/'.rawurlencode($sessionId), [
            'expand' => ['subscription', 'payment_intent.latest_charge', 'invoice'],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Stripe did not answer about the checkout (HTTP '.$response->status().').');
        }

        $session = (array) $response->json();
        $paid = ($session['status'] ?? null) === 'complete' && in_array($session['payment_status'] ?? null, ['paid', 'no_payment_required'], true);
        $subscription = is_array($session['subscription'] ?? null) ? $session['subscription'] : null;
        $periodEnd = $subscription['current_period_end'] ?? Arr::get($subscription ?? [], 'items.data.0.current_period_end');
        $paymentId = Arr::get($session, 'payment_intent.id') ?? (is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : null);
        $receipt = Arr::get($session, 'payment_intent.latest_charge.receipt_url') ?? Arr::get($session, 'invoice.hosted_invoice_url');

        return new PaymentResult(
            paid: $paid,
            expired: ($session['status'] ?? null) === 'expired',
            totalCents: is_int($session['amount_total'] ?? null) ? $session['amount_total'] : null,
            taxCents: is_int(Arr::get($session, 'total_details.amount_tax')) ? Arr::get($session, 'total_details.amount_tax') : null,
            paymentId: is_string($paymentId) ? $paymentId : (is_string(Arr::get($session, 'invoice.payment_intent')) ? Arr::get($session, 'invoice.payment_intent') : null),
            subscriptionId: is_string($subscription['id'] ?? null) ? $subscription['id'] : null,
            periodEnd: is_int($periodEnd) ? CarbonImmutable::createFromTimestamp($periodEnd) : null,
            receiptUrl: is_string($receipt) ? $receipt : null,
        );
    }

    public function portal(User $buyer, string $returnUrl): ?string
    {
        $customer = GatewayCustomer::query()->where('user_id', $buyer->id)->where('gateway', 'stripe')->where('environment', $this->environment)->value('customer_id');

        if (! is_string($customer)) {
            return null;
        }

        $session = $this->post('/billing_portal/sessions', ['customer' => $customer, 'return_url' => $returnUrl]);

        return is_string($session['url'] ?? null) ? $session['url'] : null;
    }

    public function event(string $body, ?string $signature): ?GatewayEvent
    {
        if ($this->webhookSecret === '' || ! self::verify($body, (string) $signature, $this->webhookSecret, time())) {
            return null;
        }

        $event = json_decode($body, true);

        if (! is_array($event) || ! is_string($event['id'] ?? null)) {
            return null;
        }

        $object = (array) Arr::get($event, 'data.object', []);
        $type = is_string($event['type'] ?? null) ? $event['type'] : '';
        $subscription = is_string($object['subscription'] ?? null) ? $object['subscription'] : Arr::get($object, 'parent.subscription_details.subscription');
        $end = Arr::get($object, 'lines.data.0.period.end');

        return match ($type) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' => new GatewayEvent($event['id'], 'checkout_completed', sessionId: is_string($object['id'] ?? null) ? $object['id'] : null),
            'invoice.paid' => new GatewayEvent($event['id'], 'renewed', subscriptionId: is_string($subscription) ? $subscription : null, periodEnd: is_int($end) ? CarbonImmutable::createFromTimestamp($end) : null),
            'invoice.payment_failed' => new GatewayEvent($event['id'], 'payment_failed', subscriptionId: is_string($subscription) ? $subscription : null),
            'customer.subscription.deleted' => new GatewayEvent($event['id'], 'canceled', subscriptionId: is_string($object['id'] ?? null) ? $object['id'] : null),
            'charge.refunded' => ($object['refunded'] ?? false) === true
                ? new GatewayEvent($event['id'], 'refunded', paymentId: is_string($object['payment_intent'] ?? null) ? $object['payment_intent'] : null)
                : new GatewayEvent($event['id'], 'ignored'),
            default => new GatewayEvent($event['id'], 'ignored'),
        };
    }

    /** `Stripe-Signature: t=<time>,v1=<HMAC-SHA256 of "<time>.<body>">`. */
    public static function verify(string $body, string $header, string $secret, int $now): bool
    {
        $time = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't' && ctype_digit($value)) {
                $time = (int) $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($time === null || abs($now - $time) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $time.'.'.$body, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function customer(User $buyer): string
    {
        $existing = GatewayCustomer::query()->where('user_id', $buyer->id)->where('gateway', 'stripe')->where('environment', $this->environment)->value('customer_id');

        if (is_string($existing)) {
            return $existing;
        }

        $customer = $this->post('/customers', array_filter([
            'email' => $buyer->email,
            'name' => $buyer->name,
            'phone' => $buyer->phone,
            'metadata' => ['reos_user' => (string) $buyer->id],
        ]), 'customer:'.$buyer->id.':'.$this->environment);
        $id = $customer['id'] ?? null;

        if (! is_string($id)) {
            throw new RuntimeException('Stripe created no customer.');
        }

        GatewayCustomer::query()->firstOrCreate(['user_id' => $buyer->id, 'gateway' => 'stripe', 'environment' => $this->environment], ['customer_id' => $id]);

        return $id;
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
        return $this->http->baseUrl(self::API)->withToken($this->secretKey)->acceptJson()->timeout(20)->retry(2, 300, throw: false);
    }
}
