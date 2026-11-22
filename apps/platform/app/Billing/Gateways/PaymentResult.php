<?php

declare(strict_types=1);

namespace App\Billing\Gateways;

use Carbon\CarbonImmutable;

/** What the gateway says about a checkout: paid or not, totals, and the subscription it started. */
final readonly class PaymentResult
{
    public function __construct(
        public bool $paid,
        public bool $expired = false,
        public ?int $totalCents = null,
        public ?int $taxCents = null,
        public ?string $paymentId = null,
        public ?string $subscriptionId = null,
        public ?CarbonImmutable $periodEnd = null,
        public ?string $receiptUrl = null,
    ) {}
}
