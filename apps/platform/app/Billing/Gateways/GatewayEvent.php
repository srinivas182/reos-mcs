<?php

declare(strict_types=1);

namespace App\Billing\Gateways;

use Carbon\CarbonImmutable;

/**
 * A verified webhook event in REOS's terms (B5.1). `kind`:
 * - `checkout_completed` (sessionId)
 * - `renewed` (subscriptionId, periodEnd)
 * - `payment_failed` (subscriptionId)
 * - `canceled` (subscriptionId)
 * - `refunded` (paymentId)
 * - `ignored`
 */
final readonly class GatewayEvent
{
    public function __construct(
        public string $id,
        public string $kind,
        public ?string $sessionId = null,
        public ?string $subscriptionId = null,
        public ?string $paymentId = null,
        public ?CarbonImmutable $periodEnd = null,
    ) {}
}
