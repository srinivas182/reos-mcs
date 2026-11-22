<?php

declare(strict_types=1);

namespace App\Billing\Payouts;

use App\Billing\OrderStatus;
use App\Models\ConnectedAccount;
use App\Models\Order;
use App\Models\Organisation;
use App\Models\Payout;
use App\Models\PayoutLine;
use App\Models\Product;
use App\Models\SponsorDiscount;
use App\Models\State;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * What schools, instructors and mentors earned (B5.2b): Sales and payouts
 * (school web screen 53), Earnings (instructor screen 20, mentor screen 28,
 * and the instructor's web Earnings). Refunded sales don't count.
 */
final readonly class PayoutStatements
{
    public function __construct(private ConnectedAccounts $accounts) {}

    /** The next weekly payout (Mondays). */
    public static function nextPayout(): CarbonImmutable
    {
        return CarbonImmutable::now('America/Chicago')->next(CarbonImmutable::MONDAY)->startOfDay();
    }

    /**
     * Screen 53: the school's products with prices and sponsor discounts,
     * this month's payout breakdown, its Stripe account and recent payouts.
     *
     * @return array<string, mixed>
     */
    public function school(Organisation $school, CarbonImmutable $month): array
    {
        $from = $month->startOfMonth();
        $to = $month->endOfMonth();
        $products = Product::query()->with(['prices' => static fn ($q) => $q->current()])->where('seller_organisation_id', $school->id)->orderBy('name')->get();
        $names = State::query()->pluck('name', 'code')->all();

        $sold = [];

        foreach (Order::query()->whereIn('product_id', $products->modelKeys())->where('status', OrderStatus::Paid)->whereBetween('paid_at', [$from, $to])->get(['product_id', 'state_code']) as $order) {
            $key = $order->product_id.':'.$order->state_code;
            $sold[$key] = ($sold[$key] ?? 0) + 1;
        }

        $discounts = SponsorDiscount::query()->with('sponsor:id,name')->whereIn('product_id', $products->modelKeys())->where('active', true)->get()->groupBy('product_id');

        $orders = Order::query()->whereIn('product_id', $products->modelKeys())->where('status', OrderStatus::Paid)->whereBetween('paid_at', [$from, $to]);
        $paid = (clone $orders)->get(['id', 'amount_cents', 'discount_cents']);
        $lines = PayoutLine::query()->whereIn('order_id', $paid->modelKeys())->whereNotIn('status', [PayoutLineStatus::Cancelled, PayoutLineStatus::Reversed])->get();
        $seller = $lines->where('payee_type', ConnectedAccount::ORGANISATION);
        $rows = [];

        foreach ($products as $product) {
            foreach ($product->prices as $price) {
                $rows[] = [
                    'id' => $price->id,
                    'product' => $product->name,
                    'state' => is_string($names[$price->state_code] ?? null) ? $names[$price->state_code] : $price->state_code,
                    'amount' => $price->amount_cents,
                    'floor' => $price->floor_cents,
                    'interval' => $price->interval->value,
                    'sold' => $sold[$product->id.':'.$price->state_code] ?? 0,
                    'discounts' => ($discounts->get($product->id) ?? collect())->map(static fn (SponsorDiscount $d): array => ['sponsor' => $d->sponsor->name, 'amount' => $d->amount_cents])->values()->all(),
                ];
            }
        }

        return [
            'month' => $from->format('Y-m'),
            'products' => $rows,
            'totals' => [
                'gross' => self::total($paid, 'amount_cents'),
                'discounts' => self::total($paid, 'discount_cents'),
                'platform_fee' => self::total($seller, 'platform_fee_cents'),
                'payees' => self::total($lines->where('payee_type', ConnectedAccount::USER), 'amount_cents'),
                'net' => self::total($seller, 'amount_cents'),
            ],
            'fee_percent' => RevenueSplit::platformFeePercent(),
            'account' => $this->accounts->summary($school),
            'own_account_allowed' => $this->accounts->ownAccountAllowed(),
            'pending' => $this->pending(ConnectedAccount::ORGANISATION, $school->id),
            'next_payout' => self::nextPayout()->toDateString(),
            'payouts' => $this->recentPayouts($school),
        ];
    }

    /**
     * Earnings for an instructor or mentor: net this month, the next payout,
     * each sale's share and recent payouts.
     *
     * @return array<string, mixed>
     */
    public function person(User $user, CarbonImmutable $month): array
    {
        $lines = PayoutLine::query()->with('order.product:id,name')
            ->where('payee_type', ConnectedAccount::USER)->where('payee_id', $user->id)
            ->latest('id')->limit(100)->get();
        $from = $month->startOfMonth();
        $to = $month->endOfMonth();

        return [
            'month' => $from->format('Y-m'),
            'net_this_month' => self::total($lines->filter(static fn (PayoutLine $l): bool => $l->created_at !== null && $l->created_at->between($from, $to)
                && ! in_array($l->status, [PayoutLineStatus::Cancelled, PayoutLineStatus::Reversed], true)), 'amount_cents'),
            'fee_percent' => RevenueSplit::platformFeePercent(),
            'pending' => $this->pending(ConnectedAccount::USER, $user->id),
            'next_payout' => self::nextPayout()->toDateString(),
            'account' => $this->accounts->summary($user),
            'lines' => array_values($lines->map(static fn (PayoutLine $l): array => [
                'id' => $l->id,
                'product' => $l->order->product->name,
                'role' => $l->role->value,
                'amount' => $l->amount_cents,
                'status' => $l->status->value,
                'date' => $l->created_at?->toDateString(),
            ])->all()),
            'payouts' => $this->recentPayouts($user),
        ];
    }

    /**
     * Every line of the school's sales, for "Export".
     *
     * @return list<array<int, string|int>>
     */
    public function schoolExport(Organisation $school, CarbonImmutable $month): array
    {
        $orders = Order::query()->with('product:id,name')
            ->whereHas('product', static fn ($q) => $q->where('seller_organisation_id', $school->id))
            ->whereIn('status', [OrderStatus::Paid, OrderStatus::Refunded])
            ->whereBetween('paid_at', [$month->startOfMonth(), $month->endOfMonth()])
            ->orderBy('paid_at')->get();
        $lines = PayoutLine::query()->whereIn('order_id', $orders->modelKeys())->get()->groupBy('order_id');

        $rows = [];

        foreach ($orders as $order) {
            $mine = $lines->get($order->id) ?? collect();
            $seller = $mine->firstWhere('payee_type', ConnectedAccount::ORGANISATION);

            $rows[] = [
                $order->paid_at?->toDateString() ?? '',
                $order->uuid,
                $order->product->name,
                $order->state_code,
                $order->amount_cents,
                $order->discount_cents,
                $seller instanceof PayoutLine ? $seller->platform_fee_cents : 0,
                self::total($mine->where('payee_type', ConnectedAccount::USER), 'amount_cents'),
                $seller instanceof PayoutLine ? $seller->amount_cents : 0,
                $order->status->value,
                $seller instanceof PayoutLine ? $seller->status->value : '',
            ];
        }

        return $rows;
    }

    /**
     * Screen 66's payouts card: what's waiting, who's ready, what went out this month.
     *
     * @return array{pending: int, ready: int, setting_up: int, sent_this_month: int, next_payout: string}
     */
    public function platform(): array
    {
        $accounts = ConnectedAccount::query()->get(['payouts_enabled']);

        return [
            'pending' => self::total(PayoutLine::query()->where('status', PayoutLineStatus::Pending)->get(['amount_cents']), 'amount_cents'),
            'ready' => $accounts->where('payouts_enabled', true)->count(),
            'setting_up' => $accounts->where('payouts_enabled', false)->count(),
            'sent_this_month' => self::total(Payout::query()->where('status', Payout::SENT)->where('created_at', '>=', now()->startOfMonth())->get(['amount_cents']), 'amount_cents'),
            'next_payout' => self::nextPayout()->toDateString(),
        ];
    }

    private function pending(string $type, int $id): int
    {
        return self::total(PayoutLine::query()->where('payee_type', $type)->where('payee_id', $id)->where('status', PayoutLineStatus::Pending)->get(['amount_cents']), 'amount_cents');
    }

    /**
     * Adds up a whole-cents column.
     *
     * @param  iterable<Order|PayoutLine|Payout>  $rows
     */
    private static function total(iterable $rows, string $column): int
    {
        $sum = 0;

        foreach ($rows as $row) {
            $value = $row->getAttribute($column);
            $sum += is_int($value) ? $value : 0;
        }

        return $sum;
    }

    /** @return list<array{id: string, amount: int, status: string, date: string|null}> */
    private function recentPayouts(Organisation|User $owner): array
    {
        return array_values(Payout::query()->whereHas('account', static fn ($q) => $q->where('owner_type', ConnectedAccounts::type($owner))->where('owner_id', $owner->id))
            ->latest('id')->limit(10)->get()
            ->map(static fn (Payout $p): array => ['id' => $p->uuid, 'amount' => $p->amount_cents, 'status' => $p->status, 'date' => $p->created_at?->toDateString()])
            ->all());
    }
}
