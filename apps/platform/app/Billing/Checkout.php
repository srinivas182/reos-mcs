<?php

declare(strict_types=1);

namespace App\Billing;

use App\Billing\Gateways\CheckoutSession;
use App\Billing\Gateways\Gateways;
use App\Billing\Gateways\PaymentGateway;
use App\Billing\Gateways\PaymentResult;
use App\Billing\Ledger\MoneyPostings;
use App\Billing\Payouts\RevenueSplit;
use App\Communities\Communities;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Price;
use App\Models\SponsorDiscount;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PaymentFailed;
use App\Notifications\PurchaseReceipt;
use App\Services\Ai\AiCredits;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use App\Services\Tenancy\TenantSignups;
use App\Sessions\SessionBookings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Checkout on reos.com (B5.1; screen 3, ADR 0021). The order summary shows
 * the sponsor discount (automatic, never below the price's floor); the
 * gateway's own fields take the card and work out tax. On return REOS asks
 * the gateway directly and unlocks at once (addition 5); the webhook and
 * `reos:billing:reconcile` make sure nothing is missed or done twice.
 */
final readonly class Checkout
{
    /** A renewal that arrives a little late shouldn't close access. */
    public const RENEWAL_BUFFER_DAYS = 2;

    /** Access continues this long after a failed renewal (Sprint 5 defaults). */
    public const GRACE_DAYS = 7;

    public function __construct(
        private Gateways $gateways,
        private Entitlements $entitlements,
        private Memberships $memberships,
        private AuditLogger $audit,
        private TaxCalculator $tax,
        private RevenueSplit $split,
        private MoneyPostings $money,
        private AiCredits $credits,
        private TenantSignups $signups,
    ) {}

    /**
     * What the person pays before tax, with the best sponsor discount from
     * their organisations.
     *
     * @return array{amount: int, discount: int, sponsor: Organisation|null, charge: int}
     */
    public function quote(User $buyer, Price $price): array
    {
        $organisations = array_map(static fn ($m): int => $m->organisation->id, $this->memberships->of($buyer));
        $discount = SponsorDiscount::query()
            ->with('sponsor')
            ->where('product_id', $price->product_id)
            ->where('active', true)
            ->whereIn('sponsor_organisation_id', $organisations === [] ? [0] : $organisations)
            ->orderByDesc('amount_cents')
            ->first();

        $floor = $price->floor_cents ?? 0;
        $off = 0;
        $sponsor = null;

        if ($discount !== null) {
            $off = min($discount->amount_cents, max(0, $price->amount_cents - $floor));
            $sponsor = $off > 0 ? $discount->sponsor : null;
        }

        return [
            'amount' => $price->amount_cents,
            'discount' => $off,
            'sponsor' => $sponsor,
            'charge' => $price->amount_cents - $off,
        ];
    }

    /**
     * Creates the order and the gateway's checkout.
     *
     * @return array{order: Order, session: CheckoutSession, gateway: PaymentGateway}
     */
    public function start(User $buyer, Price $price, callable $returnUrl, ?string $postalCode = null): array
    {
        $gateway = $this->gateways->route($price);

        if ($gateway === null) {
            throw ValidationException::withMessages(['price' => __('billing.errors.paused')]);
        }

        if (! $gateway->calculatesTax() && ($postalCode === null || preg_match('/^\d{5}$/', $postalCode) !== 1)) {
            throw ValidationException::withMessages(['postal_code' => __('billing.errors.postalCode')]);
        }

        if (! $price->isCurrent() || $price->product->status !== ProductStatus::Live) {
            throw ValidationException::withMessages(['price' => __('billing.errors.notForSale')]);
        }

        $quote = $this->quote($buyer, $price);

        $order = Order::query()->create([
            'uuid' => (string) Str::uuid7(),
            'user_id' => $buyer->id,
            'product_id' => $price->product_id,
            'price_id' => $price->id,
            'state_code' => $price->state_code,
            'postal_code' => $gateway->calculatesTax() ? null : $postalCode,
            'amount_cents' => $quote['amount'],
            'discount_cents' => $quote['discount'],
            'sponsor_organisation_id' => $quote['sponsor']?->id,
            'currency' => $price->currency,
            'gateway' => $gateway->id(),
            'environment' => $gateway->environment(),
        ]);

        // Gateways without built-in tax charge what Stripe's tax calculation says.
        if (! $gateway->calculatesTax()) {
            $this->tax->calculate($order);
        }

        $session = $gateway->checkout($order, $buyer, $returnUrl($order));
        $order->forceFill(['gateway_session_id' => $session->id, 'gateway_reference' => $session->id])->save();

        $this->audit->record('order.started', 'order', $order->uuid, ['product' => $price->product->key, 'state' => $price->state_code, 'charge' => $quote['charge']], $buyer);

        return ['order' => $order, 'session' => $session, 'gateway' => $gateway];
    }

    /** Asks the gateway whether the order is paid and, if so, unlocks it. Safe to call any number of times. */
    public function confirm(Order $order): Order
    {
        if ($order->status !== OrderStatus::Pending || $order->gateway_session_id === null) {
            return $order;
        }

        $gateway = $this->gateways->driver($order->gateway);

        if ($gateway === null || $gateway->environment() !== $order->environment) {
            return $order;
        }

        $result = $gateway->result($order->gateway_session_id);

        if ($result->paid) {
            return $this->fulfil($order, $result);
        }

        if ($result->expired) {
            $order->forceFill(['status' => OrderStatus::Expired])->save();
        }

        return $order;
    }

    /** Marks the order paid and grants its features, once, even if the return and the webhook arrive together. */
    public function fulfil(Order $order, PaymentResult $result): Order
    {
        $fulfilled = DB::transaction(function () use ($order, $result): bool {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $buyer = $locked->user;

            if ($locked->status !== OrderStatus::Pending || $buyer === null) {
                return false;
            }

            $locked->forceFill([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
                'total_cents' => $result->totalCents ?? $locked->chargeCents() + ($locked->tax_cents ?? 0),
                'tax_cents' => $result->taxCents ?? $locked->tax_cents,
                'gateway_payment_id' => $result->paymentId,
                'gateway_subscription_id' => $result->subscriptionId,
                'receipt_url' => $result->receiptUrl,
            ])->save();

            $product = $locked->product;
            $features = $product->features ?? [];

            if ($result->subscriptionId !== null) {
                $subscription = Subscription::query()->updateOrCreate(['gateway_subscription_id' => $result->subscriptionId], [
                    'user_id' => $locked->user_id,
                    'product_id' => $locked->product_id,
                    'price_id' => $locked->price_id,
                    'order_id' => $locked->id,
                    'gateway' => $locked->gateway,
                    'status' => SubscriptionStatus::Active,
                    'current_period_end' => $result->periodEnd,
                ]);
                $this->entitlements->grant($buyer, $features, EntitlementSource::Subscription, $subscription->id, $locked->state_code, $result->periodEnd?->addDays(self::RENEWAL_BUFFER_DAYS));
            } else {
                $this->entitlements->grant($buyer, $features, EntitlementSource::Order, $locked->id, $locked->state_code);
            }

            $this->audit->record('order.paid', 'order', $locked->uuid, ['product' => $product->key, 'total' => $locked->total_cents, 'gateway' => $locked->gateway], $locked->user, null);

            return true;
        });

        $order->refresh();

        if ($fulfilled) {
            $this->tax->record($order);
            // What the sale earns the school, its instructors and mentors (B5.2b).
            $this->split->record($order);
            // The ledger (B5.3): the money, and a top-up pack's credits.
            $this->money->orderPaid($order);

            // A brokerage or school that signed up on the web (A6.1b).
            $this->signups->complete($order);

            // A booked 1-on-1 session (A10.2a): confirmed once paid.
            if ($order->product->kind === ProductKind::Session) {
                app(SessionBookings::class)->paid($order);
                // A paid expert session in a community (B17.2): the held RSVP is going.
                app(Communities::class)->paid($order);
            }

            if ($order->product->kind === ProductKind::CreditPack && $order->user !== null) {
                $this->credits->topUp($order->user, $order->product->credits ?? 0, $order->uuid);
            }
            $order->user?->notify(new PurchaseReceipt($order));
        }

        return $order;
    }

    /** A renewal was paid: access runs to the new period end. */
    public function renewed(string $subscriptionId, ?CarbonImmutable $periodEnd): void
    {
        $subscription = Subscription::query()->where('gateway_subscription_id', $subscriptionId)->first();

        if ($subscription === null) {
            return;
        }

        $end = $periodEnd ?? CarbonImmutable::now()->addMonth();
        $subscription->forceFill(['status' => SubscriptionStatus::Active, 'current_period_end' => $end, 'grace_ends_at' => null])->save();
        $this->entitlements->extend(EntitlementSource::Subscription, $subscription->id, $end->addDays(self::RENEWAL_BUFFER_DAYS));
    }

    /** A renewal failed: access continues for the grace period, and the person is told how to fix it. */
    public function paymentFailed(string $subscriptionId): void
    {
        $subscription = Subscription::query()->where('gateway_subscription_id', $subscriptionId)->first();

        if ($subscription === null || $subscription->status === SubscriptionStatus::Canceled) {
            return;
        }

        $grace = $subscription->grace_ends_at ?? now()->addDays(self::GRACE_DAYS);
        $subscription->forceFill(['status' => SubscriptionStatus::PastDue, 'grace_ends_at' => $grace])->save();
        $this->entitlements->extend(EntitlementSource::Subscription, $subscription->id, $grace);
        $subscription->user->notify(new PaymentFailed($subscription));
    }

    public function canceled(string $subscriptionId): void
    {
        $subscription = Subscription::query()->where('gateway_subscription_id', $subscriptionId)->first();

        if ($subscription === null) {
            return;
        }

        $subscription->forceFill(['status' => SubscriptionStatus::Canceled, 'canceled_at' => now()])->save();
        $this->entitlements->extend(EntitlementSource::Subscription, $subscription->id, now());
    }

    /** Refunded in full at the gateway: the order's access ends at once. */
    public function refunded(string $paymentId): void
    {
        $order = Order::query()->where('gateway_payment_id', $paymentId)->first();

        if ($order === null || $order->status === OrderStatus::Refunded) {
            return;
        }

        $order->forceFill(['status' => OrderStatus::Refunded])->save();
        $this->entitlements->revoke(EntitlementSource::Order, $order->id);

        $subscription = Subscription::query()->where('order_id', $order->id)->first();

        if ($subscription !== null) {
            $this->entitlements->revoke(EntitlementSource::Subscription, $subscription->id);
        }

        $this->split->reverse($order);
        $this->money->fullRefund($order);
        $this->audit->record('order.refunded', 'order', $order->uuid, ['total' => $order->total_cents], null, null);
    }
}
