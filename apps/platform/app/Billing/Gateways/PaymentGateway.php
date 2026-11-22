<?php

declare(strict_types=1);

namespace App\Billing\Gateways;

use App\Models\Order;
use App\Models\User;

/**
 * A payment gateway (B5.1, ADR 0021). Card details are only ever entered in
 * the gateway's own fields (PCI SAQ A); REOS sees ids and totals. B5.2 adds
 * PayPal and Authorize.net behind the same interface.
 */
interface PaymentGateway
{
    public function id(): string;

    /** sandbox or live (simulator outside production). */
    public function environment(): string;

    /** Whether it can take monthly or yearly plans. */
    public function supportsRecurring(): bool;

    /** Whether the gateway works out tax itself (Stripe Tax); otherwise REOS asks Stripe's tax calculation first. */
    public function calculatesTax(): bool;

    /** Starts a checkout for the order; tax is worked out by the gateway. */
    public function checkout(Order $order, User $buyer, string $returnUrl): CheckoutSession;

    /** Asks the gateway directly whether the checkout is paid (no waiting for the webhook). */
    public function result(string $sessionId): PaymentResult;

    /** A link to manage cards, cancel and see invoices at the gateway, if it has one. */
    public function portal(User $buyer, string $returnUrl): ?string;

    /** Checks the signature and reads the event; null when it isn't genuine. */
    public function event(string $body, ?string $signature): ?GatewayEvent;
}
