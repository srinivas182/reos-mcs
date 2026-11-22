<?php

declare(strict_types=1);

namespace App\Billing;

use App\Billing\Gateways\Gateways;
use App\Billing\Ledger\MoneyPostings;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Refunds from REOS (B5.3; screen 66's Orders and refunds). The policy
 * (`RefundPolicy`, set on screen 66; Sprint 5 defaults): full and partial
 * refunds, a full refund within 7 days of purchase if less than a quarter
 * of the course is done. The super admin can allow full refunds only, or no
 * refunds. Course progress arrives with the player (Sprint 8, which adds
 * the progress check here), so today the check is the date; a refund outside the policy
 * needs its reason and is marked as such. A full refund ends access and
 * reverses the order in the ledger and its shares; a partial one keeps
 * access and comes out of REOS's revenue. Every refund is audited.
 */
final readonly class Refunds
{
    public function __construct(
        private RefundPolicy $settings,
        private Gateways $gateways,
        private Checkout $checkout,
        private MoneyPostings $money,
        private AuditLogger $audit,
    ) {}

    /**
     * What the refund dialog shows: what's left to refund and the policy check.
     *
     * @return array{mode: string, refundable: int, days: int, window_days: int, within_policy: bool}
     */
    public function policy(Order $order): array
    {
        $days = $order->paid_at === null ? 0 : (int) $order->paid_at->diffInDays(now());
        $settings = $this->settings->get();
        $window = $settings['window_days'];

        return [
            // What screen 66 allows: full and partial, full only, or none.
            'mode' => $settings['mode'],
            'refundable' => $this->refundable($order),
            'days' => $days,
            'window_days' => $window,
            'within_policy' => $order->paid_at !== null && $days <= $window,
        ];
    }

    public function refundable(Order $order): int
    {
        $refunded = (int) Refund::query()->where('order_id', $order->id)->where('status', 'done')->sum('amount_cents');

        return max(0, ($order->total_cents ?? 0) - $refunded);
    }

    /** Refunds `$amount` cents (null: everything left). */
    public function refund(Order $order, ?int $amount, string $reason, User $actor): Refund
    {
        if ($order->status !== OrderStatus::Paid) {
            throw ValidationException::withMessages(['amount' => __('billing.refunds.errors.notPaid')]);
        }

        $policy = $this->policy($order);
        $amount ??= $policy['refundable'];

        if ($policy['mode'] === RefundPolicy::NONE) {
            throw ValidationException::withMessages(['amount' => __('billing.refunds.errors.disabled')]);
        }

        if ($amount < 1 || $amount > $policy['refundable']) {
            throw ValidationException::withMessages(['amount' => __('billing.refunds.errors.amount')]);
        }

        if ($policy['mode'] === RefundPolicy::FULL_ONLY && ($amount !== $policy['refundable'] || $policy['refundable'] !== ($order->total_cents ?? 0))) {
            throw ValidationException::withMessages(['amount' => __('billing.refunds.errors.fullOnly')]);
        }

        $gateway = $this->gateways->driver($order->gateway);

        if ($gateway === null || $gateway->environment() !== $order->environment) {
            throw ValidationException::withMessages(['amount' => __('billing.refunds.errors.gateway')]);
        }

        $full = $amount === $policy['refundable'] && $policy['refundable'] === ($order->total_cents ?? 0);
        $refund = Refund::query()->create([
            'uuid' => (string) Str::uuid(), 'order_id' => $order->id, 'amount_cents' => $amount, 'full' => $full,
            'reason' => $reason, 'status' => 'pending', 'within_policy' => $full && $policy['within_policy'], 'created_by' => $actor->id,
        ]);

        try {
            $id = $gateway->refund($order, $amount, $refund->uuid);
        } catch (Throwable $e) {
            report($e);
            $refund->forceFill(['status' => 'failed'])->save();

            throw ValidationException::withMessages(['amount' => __('billing.refunds.errors.failed')]);
        }

        DB::transaction(function () use ($refund, $id, $order, $full, $amount): void {
            $refund->forceFill(['status' => 'done', 'gateway_refund_id' => $id])->save();

            if ($full && $order->gateway_payment_id !== null) {
                // Access ends, shares are cancelled or taken back, the ledger reverses the order.
                $this->checkout->refunded($order->gateway_payment_id);
            } else {
                $this->money->partialRefund($order, $amount, $refund->uuid);
            }
        });

        $this->audit->record('order.refund_issued', 'order', $order->uuid, [
            'amount' => $amount, 'full' => $full, 'within_policy' => $refund->within_policy, 'reason' => $reason,
        ], $actor);

        return $refund;
    }
}
