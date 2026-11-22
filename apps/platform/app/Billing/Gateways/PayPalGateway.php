<?php

declare(strict_types=1);

namespace App\Billing\Gateways;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * PayPal (B5.2a): Orders API v2 with PayPal's own hosted approval, so card
 * and PayPal details never touch REOS. One-time purchases only (plans stay
 * on Stripe). Tax comes from Stripe's tax calculation and is part of the
 * amount. Webhooks are verified with PayPal's verify-webhook-signature.
 */
final readonly class PayPalGateway implements PaymentGateway
{
    public function __construct(
        private Http $http,
        private string $clientId,
        private string $clientSecret,
        private string $webhookId,
        private string $environment,
    ) {}

    public function id(): string
    {
        return 'paypal';
    }

    public function environment(): string
    {
        return $this->environment;
    }

    public function supportsRecurring(): bool
    {
        return false;
    }

    public function calculatesTax(): bool
    {
        return false;
    }

    public function checkout(Order $order, User $buyer, string $returnUrl): CheckoutSession
    {
        // Stripe's session placeholder means nothing here; REOS finds the order by its own link.
        $returnUrl = str_replace('?session_id={CHECKOUT_SESSION_ID}', '', $returnUrl);
        $tax = $order->tax_cents ?? 0;
        $response = $this->api()->withHeaders(['PayPal-Request-Id' => 'checkout-'.$order->uuid])->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $order->uuid,
                'custom_id' => $order->uuid,
                'invoice_id' => $order->uuid,
                'description' => mb_substr($order->product->name, 0, 127),
                'amount' => [
                    'currency_code' => strtoupper($order->currency),
                    'value' => self::money($order->chargeCents() + $tax),
                    'breakdown' => [
                        'item_total' => ['currency_code' => strtoupper($order->currency), 'value' => self::money($order->chargeCents())],
                        'tax_total' => ['currency_code' => strtoupper($order->currency), 'value' => self::money($tax)],
                    ],
                ],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $returnUrl,
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
                'brand_name' => 'REOS',
            ]]],
        ]);

        $id = $response->json('id');
        $approve = collect((array) $response->json('links'))->first(static fn (mixed $link): bool => is_array($link) && in_array($link['rel'] ?? null, ['payer-action', 'approve'], true));

        if (! $response->successful() || ! is_string($id) || ! is_array($approve) || ! is_string($approve['href'] ?? null)) {
            throw new RuntimeException('PayPal started no checkout (HTTP '.$response->status().').');
        }

        return new CheckoutSession($id, redirect: ['url' => $approve['href'], 'method' => 'GET', 'fields' => []]);
    }

    /** Back from approving: capture the payment (once; PayPal answers a repeat with the same capture). */
    public function result(string $sessionId): PaymentResult
    {
        $order = $this->api()->get('/v2/checkout/orders/'.rawurlencode($sessionId));
        $status = $order->json('status');

        if ($status === 'APPROVED') {
            $order = $this->api()->withHeaders(['PayPal-Request-Id' => 'capture-'.$sessionId])->post('/v2/checkout/orders/'.rawurlencode($sessionId).'/capture', (object) []);
            $status = $order->json('status');
        }

        $capture = (array) Arr::get((array) $order->json(), 'purchase_units.0.payments.captures.0', []);
        $paid = $status === 'COMPLETED' && ($capture['status'] ?? null) === 'COMPLETED';
        $total = Arr::get($capture, 'amount.value');

        return new PaymentResult(
            paid: $paid,
            expired: in_array($status, ['VOIDED'], true),
            totalCents: is_numeric($total) ? (int) round((float) $total * 100) : null,
            paymentId: is_string($capture['id'] ?? null) ? $capture['id'] : null,
        );
    }

    public function portal(User $buyer, string $returnUrl): ?string
    {
        return null;
    }

    public function event(string $body, ?string $signature): ?GatewayEvent
    {
        $headers = request()->headers;
        $event = json_decode($body, true);

        if ($this->webhookId === '' || ! is_array($event) || ! is_string($event['id'] ?? null)) {
            return null;
        }

        $verified = $this->api()->post('/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $headers->get('PAYPAL-AUTH-ALGO'),
            'cert_url' => $headers->get('PAYPAL-CERT-URL'),
            'transmission_id' => $headers->get('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig' => $headers->get('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $headers->get('PAYPAL-TRANSMISSION-TIME'),
            'webhook_id' => $this->webhookId,
            'webhook_event' => $event,
        ])->json('verification_status');

        if ($verified !== 'SUCCESS') {
            return null;
        }

        $resource = (array) ($event['resource'] ?? []);
        $orderId = Arr::get($resource, 'supplementary_data.related_ids.order_id');

        return match ($event['event_type'] ?? null) {
            'CHECKOUT.ORDER.APPROVED' => new GatewayEvent($event['id'], 'checkout_completed', sessionId: is_string($resource['id'] ?? null) ? $resource['id'] : null),
            'PAYMENT.CAPTURE.COMPLETED' => new GatewayEvent($event['id'], 'checkout_completed', sessionId: is_string($orderId) ? $orderId : null),
            'PAYMENT.CAPTURE.REFUNDED' => new GatewayEvent($event['id'], 'refunded', paymentId: self::refundedCapture($resource)),
            default => new GatewayEvent($event['id'], 'ignored'),
        };
    }

    public function refund(Order $order, int $amountCents, string $key): string
    {
        if ($order->gateway_payment_id === null) {
            throw new RuntimeException('PayPal has no capture for this order to refund.');
        }

        $response = $this->api()->withHeaders(['PayPal-Request-Id' => 'refund-'.$key])
            ->post('/v2/payments/captures/'.rawurlencode($order->gateway_payment_id).'/refund', [
                'amount' => ['value' => self::money($amountCents), 'currency_code' => strtoupper($order->currency)],
                'invoice_id' => 'refund-'.$key,
            ]);
        $id = $response->json('id');

        if (! $response->successful() || ! is_string($id)) {
            throw new RuntimeException('PayPal made no refund (HTTP '.$response->status().').');
        }

        return $id;
    }

    /** @param  array<mixed>  $resource */
    private static function refundedCapture(array $resource): ?string
    {
        $link = collect((array) ($resource['links'] ?? []))->first(static fn (mixed $l): bool => is_array($l) && ($l['rel'] ?? null) === 'up');
        $href = is_array($link) && is_string($link['href'] ?? null) ? $link['href'] : '';

        return preg_match('#/captures/([A-Z0-9]+)#', $href, $m) === 1 ? $m[1] : null;
    }

    private static function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function base(): string
    {
        return $this->environment === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    private function api(): PendingRequest
    {
        $token = Cache::remember('billing.paypal.token.'.$this->environment.'.'.hash('sha256', $this->clientId), now()->addMinutes(30), function (): string {
            $response = $this->http->asForm()->withBasicAuth($this->clientId, $this->clientSecret)->timeout(20)
                ->post($this->base().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
            $token = $response->json('access_token');

            if (! is_string($token)) {
                throw new RuntimeException('PayPal refused the keys (HTTP '.$response->status().').');
            }

            return $token;
        });

        return $this->http->baseUrl($this->base())->withToken($token)->acceptJson()->asJson()->timeout(20)->retry(2, 300, throw: false);
    }
}
