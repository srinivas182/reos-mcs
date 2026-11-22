<?php

declare(strict_types=1);

namespace App\Billing\Ledger;

use App\Models\ConnectedAccount;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Payout;
use App\Models\PayoutLine;
use App\Models\User;

/**
 * Money in the ledger (B5.3), in USD cents. A paid order puts what the
 * buyer paid in the gateway's clearing account and divides it: sales tax
 * owed, REOS's revenue (gross of the sponsor discount, which is its own
 * entry), and what the school, its instructors and mentors earned. A
 * payout moves earnings out of clearing; a full refund reverses the order
 * exactly; a partial refund comes out of REOS's revenue. Each is posted
 * once per order, payout or refund.
 */
final readonly class MoneyPostings
{
    public function __construct(private Ledger $ledger) {}

    public function orderPaid(Order $order): void
    {
        if ($this->ledger->posted('order_paid', 'order', $order->uuid)) {
            return;
        }

        $tax = $order->tax_cents ?? 0;
        $charge = max(0, ($order->total_cents ?? $order->chargeCents() + $tax) - $tax);
        $discount = $order->discount_cents;
        $lines = [
            [$this->ledger->clearing($order->gateway, $order->environment), $charge + $tax],
            [$this->ledger->platform('sales_tax'), -$tax],
            [$this->ledger->platform('sponsor_discounts'), $discount],
        ];
        $shared = 0;

        foreach (PayoutLine::query()->where('order_id', $order->id)->get() as $line) {
            $payee = $this->payee($line->payee_type, $line->payee_id);

            if ($payee !== null) {
                $lines[] = [$payee, -$line->amount_cents];
                $shared += $line->amount_cents;
            }
        }

        $lines[] = [$this->ledger->platform('revenue'), -($charge + $discount - $shared)];
        $this->ledger->post('order_paid', $lines, 'order', $order->uuid);
    }

    /** A refund in full: every entry of the order reversed. */
    public function fullRefund(Order $order): void
    {
        if ($this->ledger->posted('refund_full', 'order', $order->uuid)) {
            return;
        }

        $entries = LedgerEntry::query()->with('account')->where('reason', 'order_paid')->where('reference_type', 'order')->where('reference_id', $order->uuid)->get();
        $lines = [];

        foreach ($entries as $entry) {
            $lines[] = [$entry->account, -$entry->amount];
        }

        if ($lines !== []) {
            $this->ledger->post('refund_full', $lines, 'order', $order->uuid);
        }
    }

    /** Part of an order refunded: REOS gives it back from its revenue; access and shares stay. */
    public function partialRefund(Order $order, int $amount, string $refundUuid): void
    {
        if ($this->ledger->posted('refund_partial', 'refund', $refundUuid)) {
            return;
        }

        $this->ledger->post('refund_partial', [
            [$this->ledger->clearing($order->gateway, $order->environment), -$amount],
            [$this->ledger->platform('refunds'), $amount],
        ], 'refund', $refundUuid, $order->uuid);
    }

    public function payoutSent(Payout $payout): void
    {
        $account = $payout->account;
        $payee = $this->payee($account->owner_type, $account->owner_id);

        if ($payee === null || $this->ledger->posted('payout', 'payout', $payout->uuid)) {
            return;
        }

        $this->ledger->post('payout', [
            [$payee, $payout->amount_cents],
            [$this->ledger->clearing('stripe', $payout->environment), -$payout->amount_cents],
        ], 'payout', $payout->uuid);
    }

    /** A paid share taken back after a refund. */
    public function payoutReversed(PayoutLine $line, Payout $payout): void
    {
        $payee = $this->payee($line->payee_type, $line->payee_id);

        if ($payee === null || $this->ledger->posted('payout_reversed', 'payout_line', (string) $line->id)) {
            return;
        }

        $this->ledger->post('payout_reversed', [
            [$payee, -$line->amount_cents],
            [$this->ledger->clearing('stripe', $payout->environment), $line->amount_cents],
        ], 'payout_line', (string) $line->id);
    }

    private function payee(string $type, int $id): ?LedgerAccount
    {
        if ($type === ConnectedAccount::ORGANISATION) {
            $organisation = Organisation::query()->find($id);

            return $organisation === null ? null : $this->ledger->organisation($organisation, 'usd');
        }

        $user = User::query()->find($id);

        return $user === null ? null : $this->ledger->person($user, 'usd');
    }
}
