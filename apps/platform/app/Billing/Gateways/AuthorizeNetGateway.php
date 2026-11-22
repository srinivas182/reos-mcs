<?php

declare(strict_types=1);

namespace App\Billing\Gateways;

use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as Http;
use RuntimeException;

/**
 * Authorize.net (B5.2a): Accept Hosted, the gateway's own payment page, so
 * card data never touches REOS (PCI SAQ A). One-time purchases. The order's
 * invoice number ties the payment back; on return REOS looks it up among
 * the account's recent transactions. Webhooks are signed with HMAC-SHA512
 * (`X-ANET-Signature`, the signature key).
 */
final readonly class AuthorizeNetGateway implements PaymentGateway
{
    public function __construct(
        private Http $http,
        private string $loginId,
        private string $transactionKey,
        private string $signatureKey,
        private string $environment,
    ) {}

    public function id(): string
    {
        return 'authorize-net';
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
        $invoice = self::invoice($order);
        $total = $order->chargeCents() + ($order->tax_cents ?? 0);

        $response = $this->call('getHostedPaymentPageRequest', [
            'transactionRequest' => [
                'transactionType' => 'authCaptureTransaction',
                'amount' => number_format($total / 100, 2, '.', ''),
                'order' => ['invoiceNumber' => $invoice, 'description' => mb_substr($order->product->name, 0, 255)],
                'tax' => ['amount' => number_format(($order->tax_cents ?? 0) / 100, 2, '.', ''), 'name' => 'Sales tax'],
                'customer' => array_filter(['email' => $buyer->email]),
            ],
            'hostedPaymentSettings' => ['setting' => [
                ['settingName' => 'hostedPaymentReturnOptions', 'settingValue' => json_encode(['showReceipt' => false, 'url' => $returnUrl, 'urlText' => __('billing.hosted.continue'), 'cancelUrl' => $returnUrl, 'cancelUrlText' => __('billing.hosted.cancel')], JSON_THROW_ON_ERROR)],
                ['settingName' => 'hostedPaymentButtonOptions', 'settingValue' => json_encode(['text' => __('billing.hosted.pay')], JSON_THROW_ON_ERROR)],
                ['settingName' => 'hostedPaymentOrderOptions', 'settingValue' => json_encode(['show' => true, 'merchantName' => 'REOS'], JSON_THROW_ON_ERROR)],
            ]],
        ]);

        $token = $response['token'] ?? null;

        if (! is_string($token)) {
            throw new RuntimeException('Authorize.net started no checkout.');
        }

        $page = $this->environment === 'live' ? 'https://accept.authorize.net/payment/payment' : 'https://test.authorize.net/payment/payment';

        return new CheckoutSession($invoice, redirect: ['url' => $page, 'method' => 'POST', 'fields' => ['token' => $token]]);
    }

    /** Finds the order's invoice number among the account's unsettled transactions (today's payments). */
    public function result(string $sessionId): PaymentResult
    {
        $response = $this->call('getUnsettledTransactionListRequest', ['sorting' => ['orderBy' => 'submitTimeUTC', 'orderDescending' => true], 'paging' => ['limit' => 1000, 'offset' => 1]]);

        foreach ((array) ($response['transactions'] ?? []) as $transaction) {
            if (! is_array($transaction) || ($transaction['invoiceNumber'] ?? null) !== $sessionId) {
                continue;
            }

            $ok = in_array($transaction['transactionStatus'] ?? null, ['capturedPendingSettlement', 'settledSuccessfully'], true);
            $amount = $transaction['settleAmount'] ?? null;

            return new PaymentResult(
                paid: $ok,
                totalCents: is_numeric($amount) ? (int) round((float) $amount * 100) : null,
                paymentId: is_string($transaction['transId'] ?? null) ? $transaction['transId'] : null,
            );
        }

        return new PaymentResult(paid: false);
    }

    public function portal(User $buyer, string $returnUrl): ?string
    {
        return null;
    }

    public function event(string $body, ?string $signature): ?GatewayEvent
    {
        $expected = 'SHA512='.strtoupper(hash_hmac('sha512', $body, $this->signatureKey));

        if ($this->signatureKey === '' || $signature === null || ! hash_equals($expected, strtoupper(trim($signature)))) {
            return null;
        }

        $event = json_decode($body, true);

        if (! is_array($event) || ! is_string($event['notificationId'] ?? null)) {
            return null;
        }

        $payload = (array) ($event['payload'] ?? []);

        return match ($event['eventType'] ?? null) {
            'net.authorize.payment.authcapture.created' => new GatewayEvent($event['notificationId'], 'checkout_completed', sessionId: is_string($payload['invoiceNumber'] ?? null) ? $payload['invoiceNumber'] : null),
            'net.authorize.payment.refund.created', 'net.authorize.payment.void.created' => new GatewayEvent($event['notificationId'], 'refunded', paymentId: is_string($payload['id'] ?? null) ? $payload['id'] : null),
            default => new GatewayEvent($event['notificationId'], 'ignored'),
        };
    }

    /**
     * Today's payments (not yet settled) are voided in full; settled ones
     * are refunded to the card's last four digits, as Authorize.net asks.
     */
    public function refund(Order $order, int $amountCents, string $key): string
    {
        $transaction = $order->gateway_payment_id ?? throw new RuntimeException('Authorize.net has no transaction for this order to refund.');
        $details = $this->call('getTransactionDetailsRequest', ['transId' => $transaction]);
        $info = is_array($details['transaction'] ?? null) ? $details['transaction'] : [];
        $total = is_numeric($info['settleAmount'] ?? null) ? (int) round((float) $info['settleAmount'] * 100) : null;
        $unsettled = in_array($info['transactionStatus'] ?? null, ['capturedPendingSettlement', 'authorizedPendingCapture'], true);

        if ($unsettled && $total === $amountCents) {
            $request = ['transactionType' => 'voidTransaction', 'refTransId' => $transaction];
        } else {
            $payment = is_array($info['payment'] ?? null) ? $info['payment'] : [];
            $card = is_array($payment['creditCard'] ?? null) ? $payment['creditCard'] : [];
            $number = is_string($card['cardNumber'] ?? null) ? substr($card['cardNumber'], -4) : '';
            $request = [
                'transactionType' => 'refundTransaction',
                'amount' => number_format($amountCents / 100, 2, '.', ''),
                'payment' => ['creditCard' => ['cardNumber' => $number, 'expirationDate' => 'XXXX']],
                'refTransId' => $transaction,
            ];
        }

        $response = $this->call('createTransactionRequest', ['refId' => substr(str_replace('-', '', $key), 0, 20), 'transactionRequest' => $request]);
        $result = is_array($response['transactionResponse'] ?? null) ? $response['transactionResponse'] : [];
        $id = $result['transId'] ?? null;

        if (! is_string($id) || ($result['responseCode'] ?? null) !== '1') {
            throw new RuntimeException('Authorize.net made no refund.');
        }

        return $id;
    }

    /** Invoice numbers are at most 20 characters: R, the date and the last ten of the order's id (17). */
    public static function invoice(Order $order): string
    {
        return 'R'.CarbonImmutable::now()->format('ymd').strtoupper(substr(str_replace('-', '', $order->uuid), -10));
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<mixed>
     */
    private function call(string $name, array $request): array
    {
        $url = $this->environment === 'live' ? 'https://api.authorize.net/xml/v1/request.api' : 'https://apitest.authorize.net/xml/v1/request.api';
        $response = $this->http->asJson()->acceptJson()->timeout(20)->retry(2, 300, throw: false)->post($url, [
            $name => ['merchantAuthentication' => ['name' => $this->loginId, 'transactionKey' => $this->transactionKey], ...$request],
        ]);

        // Authorize.net starts its JSON with a byte-order mark.
        $data = json_decode(ltrim($response->body(), "\xEF\xBB\xBF"), true);

        $messages = is_array($data) ? ($data['messages'] ?? null) : null;

        if (! is_array($data) || ! is_array($messages) || ($messages['resultCode'] ?? null) !== 'Ok') {
            throw new RuntimeException('Authorize.net refused the request.');
        }

        return $data;
    }
}
