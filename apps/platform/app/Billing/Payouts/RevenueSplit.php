<?php

declare(strict_types=1);

namespace App\Billing\Payouts;

use App\Billing\Ledger\MoneyPostings;
use App\Models\ConnectedAccount;
use App\Models\Order;
use App\Models\Payout;
use App\Models\PayoutLine;
use App\Models\ProductSplit;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * What each sale earns (B5.2b). For a product a school sells, REOS keeps
 * its platform fee (screen 66: 10%, which also covers card fees); each
 * instructor or mentor on the product gets their percent of the rest; the
 * school keeps what's left. Tax is never shared (it goes to the state).
 * Products REOS sells earn no one else anything. Lines are written once
 * per order and payee; a refund cancels unpaid lines and reverses paid ones.
 */
final readonly class RevenueSplit
{
    public function __construct(private PayoutProviders $providers, private AuditLogger $audit, private MoneyPostings $money) {}

    public static function platformFeePercent(): int
    {
        return config()->integer('reos.billing.platform_fee_percent', 10);
    }

    /**
     * The split of an amount before it's written: [platform fee, school, [user id => share]].
     *
     * @param  iterable<ProductSplit>  $splits
     * @return array{fee: int, seller: int, payees: array<int, int>}
     */
    public static function divide(int $collected, iterable $splits): array
    {
        $fee = intdiv($collected * self::platformFeePercent() + 50, 100);
        $rest = $collected - $fee;
        $payees = [];

        foreach ($splits as $split) {
            $payees[$split->payee_user_id] = intdiv($rest * $split->percent, 100);
        }

        return ['fee' => $fee, 'seller' => $rest - array_sum($payees), 'payees' => $payees];
    }

    /** Writes the order's lines once it's paid. */
    public function record(Order $order): void
    {
        $product = $order->product;

        if ($product->seller_organisation_id === null) {
            return;
        }

        $collected = max(0, ($order->total_cents ?? $order->chargeCents()) - ($order->tax_cents ?? 0));
        $splits = $product->splits()->get();
        $division = self::divide($collected, $splits);

        DB::transaction(function () use ($order, $product, $collected, $splits, $division): void {
            PayoutLine::query()->firstOrCreate(
                ['order_id' => $order->id, 'payee_type' => ConnectedAccount::ORGANISATION, 'payee_id' => $product->seller_organisation_id],
                ['role' => PayeeRole::Seller, 'gross_cents' => $collected - array_sum($division['payees']), 'platform_fee_cents' => $division['fee'], 'amount_cents' => $division['seller'], 'status' => PayoutLineStatus::Pending],
            );

            foreach ($splits as $split) {
                $share = $division['payees'][$split->payee_user_id] ?? 0;

                PayoutLine::query()->firstOrCreate(
                    ['order_id' => $order->id, 'payee_type' => ConnectedAccount::USER, 'payee_id' => $split->payee_user_id],
                    ['role' => $split->role, 'gross_cents' => $share, 'platform_fee_cents' => 0, 'amount_cents' => $share, 'status' => PayoutLineStatus::Pending],
                );
            }
        });
    }

    /** A refunded order: unpaid shares are cancelled; paid ones are taken back from the payee's Stripe account. */
    public function reverse(Order $order): void
    {
        $lines = PayoutLine::query()->with('payout.account')->where('order_id', $order->id)->whereIn('status', [PayoutLineStatus::Pending, PayoutLineStatus::Paid])->get();

        foreach ($lines as $line) {
            if ($line->status === PayoutLineStatus::Pending) {
                $line->forceFill(['status' => PayoutLineStatus::Cancelled])->save();

                continue;
            }

            $payout = $line->payout;
            $provider = $this->providers->current();

            if (! $payout instanceof Payout || $payout->transfer_id === null || $provider === null || $provider->environment() !== $payout->environment) {
                continue;
            }

            try {
                $provider->reverse($payout->transfer_id, $line->amount_cents, 'line:'.$line->id);
                $line->forceFill(['status' => PayoutLineStatus::Reversed])->save();
                $this->money->payoutReversed($line, $payout);
                $this->audit->record('payouts.reversed', 'payout', $payout->uuid, ['from' => PayoutLineStatus::Paid->value, 'to' => PayoutLineStatus::Reversed->value, 'amount' => $line->amount_cents, 'order' => $order->uuid]);
            } catch (Throwable $e) {
                // Left as paid; the daily reconciliation (B5.3) lists it.
                report($e);
            }
        }
    }
}
