<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Billing\Checkout;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Applies one verified payment event (B5.1) on the `billing` queue. Every step is safe to run twice. */
final class HandlePaymentEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public readonly string $kind,
        public readonly ?string $sessionId = null,
        public readonly ?string $subscriptionId = null,
        public readonly ?string $paymentId = null,
        public readonly ?string $periodEnd = null,
    ) {
        $this->onQueue('billing');
    }

    public function handle(Checkout $checkout): void
    {
        match ($this->kind) {
            'checkout_completed' => $this->completed($checkout),
            'renewed' => $this->subscriptionId === null ? null : $checkout->renewed($this->subscriptionId, $this->periodEnd === null ? null : CarbonImmutable::parse($this->periodEnd)),
            'payment_failed' => $this->subscriptionId === null ? null : $checkout->paymentFailed($this->subscriptionId),
            'canceled' => $this->subscriptionId === null ? null : $checkout->canceled($this->subscriptionId),
            'refunded' => $this->paymentId === null ? null : $checkout->refunded($this->paymentId),
            default => null,
        };
    }

    private function completed(Checkout $checkout): void
    {
        $order = $this->sessionId === null ? null : Order::query()->where('gateway_session_id', $this->sessionId)->first();

        if ($order !== null) {
            $checkout->confirm($order);
        }
    }
}
