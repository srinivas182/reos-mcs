<?php

declare(strict_types=1);

namespace App\Rewards;

use App\Billing\Buyer;
use App\Billing\Entitlements;
use App\Billing\EntitlementSource;
use App\Billing\Ledger\Ledger;
use App\Billing\Ledger\MoneyPostings;
use App\Billing\Ledger\Points;
use App\Billing\OrderStatus;
use App\Billing\Payouts\RevenueSplit;
use App\Billing\PriceInterval;
use App\Billing\ProductKind;
use App\Billing\ProductStatus;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Price;
use App\Models\User;
use App\Services\Ai\AiCredits;
use App\Services\Audit\AuditLogger;
use App\Services\States\PersonStates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Rewards (A13.3a, ADR 0055; agent screen 112): the points balance and what
 * it's worth (1,000 points = $10, no cash value), recent points, and what
 * points pay for inside REOS — mentor sessions (A13.1a), AI credit packs
 * and a period of membership. A redemption is an order paid with points,
 * so it's split and posted like any other sale.
 *
 * @phpstan-type Option array{price: int, kind: string, name: string, points: int, credits: int|null, interval: string, affordable: bool}
 * @phpstan-type HistoryRow array{reason: string, points: int, memo: string|null, at: string}
 * @phpstan-type Summary array{balance: int, value_cents: int, options: list<Option>, history: list<HistoryRow>}
 */
final readonly class Rewards
{
    public const GATEWAY = 'points';

    private const KINDS = [ProductKind::CreditPack, ProductKind::Plan];

    public function __construct(
        private Points $points,
        private Ledger $ledger,
        private RevenueSplit $split,
        private MoneyPostings $money,
        private AiCredits $credits,
        private Entitlements $entitlements,
        private PersonStates $states,
        private AuditLogger $audit,
    ) {}

    /** @return Summary */
    public function summary(User $agent): array
    {
        $balance = $this->points->balance($agent);
        $account = $this->ledger->find("person:{$agent->id}:points");

        return [
            'balance' => $balance,
            'value_cents' => $balance * PointRates::CENTS_PER_POINT,
            'options' => array_map(static fn (Price $p): array => [
                'price' => $p->id, 'kind' => $p->product->kind->value, 'name' => $p->product->name, 'points' => $p->amount_cents,
                'credits' => $p->product->credits, 'interval' => $p->interval->value, 'affordable' => $balance >= $p->amount_cents,
            ], $this->prices($agent)),
            'history' => $account === null ? [] : array_values(LedgerEntry::query()->where('ledger_account_id', $account->id)->orderByDesc('id')->limit(20)->get()
                ->map(static fn (LedgerEntry $e): array => ['reason' => $e->reason, 'points' => $e->amount, 'memo' => $e->memo, 'at' => $e->created_at->toIso8601String()])->all()),
        ];
    }

    /** Pays for an AI credit pack or a period of membership with points. */
    public function redeem(User $agent, int $priceId): Order
    {
        $price = null;

        foreach ($this->prices($agent) as $candidate) {
            if ($candidate->id === $priceId) {
                $price = $candidate;
            }
        }

        if ($price === null) {
            throw ValidationException::withMessages(['price' => __('rewards.errors.option')]);
        }

        $order = DB::transaction(function () use ($agent, $price): Order {
            $uuid = (string) Str::uuid7();
            $this->points->redeem($agent, $price->amount_cents, "order:{$uuid}");

            return Order::query()->create([
                'uuid' => $uuid, 'user_id' => $agent->id, 'product_id' => $price->product_id, 'price_id' => $price->id, 'state_code' => $price->state_code,
                'amount_cents' => $price->amount_cents, 'discount_cents' => 0, 'tax_cents' => 0, 'total_cents' => $price->amount_cents, 'currency' => 'usd',
                'status' => OrderStatus::Paid, 'gateway' => self::GATEWAY, 'environment' => 'live', 'paid_at' => now(),
            ]);
        });

        $product = $price->product;

        if ($product->kind === ProductKind::CreditPack) {
            $this->credits->topUp($agent, $product->credits ?? 0, $order->uuid);
        } else {
            // One period of membership; it doesn't renew on its own.
            $ends = $price->interval === PriceInterval::Year ? CarbonImmutable::now()->addYear() : CarbonImmutable::now()->addMonth();
            $this->entitlements->grant($agent, $product->features ?? [], EntitlementSource::Order, $order->id, $price->state_code, $ends);
        }

        $this->split->record($order);
        $this->money->orderPaid($order);
        $this->audit->record('points.redeemed', 'order', $order->uuid, ['product' => $product->key, 'points' => $price->amount_cents], $agent);

        return $order;
    }

    /** @return list<Price> */
    private function prices(User $agent): array
    {
        $state = $this->states->current($agent);

        return array_values(Price::query()->with('product')->current()->where('state_code', $state)
            ->whereHas('product', static fn ($q) => $q->where('buyer', Buyer::Agent)->where('status', ProductStatus::Live)->whereIn('kind', array_map(static fn (ProductKind $k): string => $k->value, self::KINDS)))
            ->orderBy('amount_cents')->get()->all());
    }
}
