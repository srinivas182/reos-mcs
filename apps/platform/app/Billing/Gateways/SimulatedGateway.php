<?php

declare(strict_types=1);

namespace App\Billing\Gateways;

use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The Simulator (outside production; B5.1): checkout shows a clearly marked
 * test panel instead of the gateway's card fields, and "Pay" marks the
 * session paid. Tax is worked out as Texas would (8.25%, demo only), so
 * receipts and the order summary look real. Never used in production.
 */
final class SimulatedGateway implements PaymentGateway
{
    /** Demo tax rate, so the summary shows a tax line. */
    public const TAX_RATE = 0.0825;

    private const TTL_HOURS = 24;

    public function id(): string
    {
        return 'stripe';
    }

    public function environment(): string
    {
        return 'simulator';
    }

    public function checkout(Order $order, User $buyer, string $returnUrl): CheckoutSession
    {
        $id = 'sim_cs_'.Str::lower(Str::random(24));
        Cache::put(self::key($id), ['order' => $order->uuid, 'paid' => false, 'recurring' => $order->price->interval->recurring(), 'interval' => $order->price->interval->value, 'charge' => $order->chargeCents()], now()->addHours(self::TTL_HOURS));

        return new CheckoutSession($id);
    }

    /** The test panel's "Pay" (only on the Simulator). */
    public static function pay(string $sessionId): bool
    {
        $session = Cache::get(self::key($sessionId));

        if (! is_array($session)) {
            return false;
        }

        $session['paid'] = true;
        $session['payment'] = 'sim_pi_'.Str::lower(Str::random(20));
        $session['subscription'] = ($session['recurring'] ?? false) === true ? 'sim_sub_'.Str::lower(Str::random(20)) : null;
        Cache::put(self::key($sessionId), $session, now()->addHours(self::TTL_HOURS));

        return true;
    }

    public function result(string $sessionId): PaymentResult
    {
        $session = Cache::get(self::key($sessionId));

        if (! is_array($session)) {
            return new PaymentResult(paid: false, expired: true);
        }

        $charge = is_int($session['charge'] ?? null) ? $session['charge'] : 0;
        $tax = (int) round($charge * self::TAX_RATE);
        $paid = ($session['paid'] ?? false) === true;
        $interval = $session['interval'] ?? 'once';

        return new PaymentResult(
            paid: $paid,
            totalCents: $charge + $tax,
            taxCents: $tax,
            paymentId: $paid && is_string($session['payment'] ?? null) ? $session['payment'] : null,
            subscriptionId: $paid && is_string($session['subscription'] ?? null) ? $session['subscription'] : null,
            periodEnd: match ($interval) {
                'month' => CarbonImmutable::now()->addMonth(),
                'year' => CarbonImmutable::now()->addYear(),
                default => null,
            },
        );
    }

    public function portal(User $buyer, string $returnUrl): ?string
    {
        return null;
    }

    public function event(string $body, ?string $signature): ?GatewayEvent
    {
        return null;
    }

    private static function key(string $sessionId): string
    {
        return 'billing.simulator.'.$sessionId;
    }
}
