<?php

declare(strict_types=1);

namespace App\Billing;

use App\Billing\Gateways\Gateways;
use App\Jobs\HandlePaymentEvent;
use App\Models\WebhookReceipt;

/**
 * The gateway's webhook (B5.1): signature checked, each event stored once
 * by its id (a repeat is acknowledged and ignored), then handled on the
 * queue. Events may arrive late or out of order; handlers are idempotent.
 */
final readonly class PaymentWebhooks
{
    public const ACCEPTED = 'accepted';

    public const DUPLICATE = 'duplicate';

    public const REFUSED = 'refused';

    public function __construct(private Gateways $gateways) {}

    public function receive(string $body, ?string $signature): string
    {
        $event = $this->gateways->current()?->event($body, $signature);

        if ($event === null) {
            return self::REFUSED;
        }

        $inserted = WebhookReceipt::query()->insertOrIgnore([
            'integration_id' => Gateways::STRIPE,
            'delivery_key' => mb_substr($event->id, 0, 255),
            'received_at' => now(),
        ]);

        if ($inserted === 0) {
            return self::DUPLICATE;
        }

        if ($event->kind !== 'ignored') {
            HandlePaymentEvent::dispatch($event->kind, $event->sessionId, $event->subscriptionId, $event->paymentId, $event->periodEnd?->toIso8601String());
        }

        return self::ACCEPTED;
    }
}
