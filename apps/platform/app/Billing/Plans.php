<?php

declare(strict_types=1);

namespace App\Billing;

use App\Models\Price;
use App\Models\Product;
use App\Models\ProductSplit;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Plans and pricing (B5.1; screen 67): REOS sets every product and its price
 * per state (decision 3). A price change ends the old price and starts the
 * new one, so the history stays and subscribers keep their price until
 * they renew. Every change is audited.
 */
final readonly class Plans
{
    public const AUDIT_SUBJECT = 'product';

    private const AUDITED = ['name', 'kind', 'buyer', 'description', 'includes', 'features', 'ai_help', 'credits', 'seats', 'seller_organisation_id'];

    public function __construct(private AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $actor): Product
    {
        $name = is_string($data['name'] ?? null) ? $data['name'] : '';
        $product = Product::query()->create([
            ...array_intersect_key($data, array_flip(self::AUDITED)),
            'key' => $this->key($name),
            'status' => ProductStatus::Draft,
        ]);

        $this->audit->record('product.created', self::AUDIT_SUBJECT, $product->key, ['name' => $product->name, 'buyer' => $product->buyer->value], $actor);

        return $product;
    }

    /** @param  array<string, mixed>  $data */
    public function update(Product $product, array $data, User $actor): Product
    {
        $before = array_intersect_key($product->attributesToArray(), array_flip(self::AUDITED));
        $product->fill(array_intersect_key($data, array_flip(self::AUDITED)));
        $changed = array_values(array_intersect(array_keys($product->getDirty()), self::AUDITED));

        if ($changed === []) {
            return $product;
        }

        $product->save();
        $after = array_intersect_key($product->attributesToArray(), array_flip($changed));

        $this->audit->record('product.updated', self::AUDIT_SUBJECT, $product->key, [
            'before' => array_intersect_key($before, array_flip($changed)),
            'after' => $after,
        ], $actor);

        return $product;
    }

    /** Live needs a current price in at least one state. */
    public function setStatus(Product $product, ProductStatus $status, User $actor): Product
    {
        if ($status === ProductStatus::Live && ! $product->prices()->current()->exists()) {
            throw ValidationException::withMessages(['status' => __('billing.errors.noPrice')]);
        }

        $from = $product->status;
        $product->forceFill(['status' => $status])->save();

        if ($from !== $status) {
            $this->audit->record('product.status_changed', self::AUDIT_SUBJECT, $product->key, ['from' => $from->value, 'to' => $status->value], $actor);
        }

        return $product;
    }

    /** A new price for one state and interval; the current one ends now and stays in the history. */
    public function setPrice(Product $product, string $state, PriceInterval $interval, int $amountCents, ?int $floorCents, User $actor): Price
    {
        if ($floorCents !== null && $floorCents > $amountCents) {
            throw ValidationException::withMessages(['floor' => __('billing.errors.floor')]);
        }

        return DB::transaction(function () use ($product, $state, $interval, $amountCents, $floorCents, $actor): Price {
            $current = Price::query()->where('product_id', $product->id)->where('state_code', $state)->where('interval', $interval)->current()->lockForUpdate()->first();
            $now = now();

            $current?->forceFill(['ends_at' => $now])->save();

            $price = Price::query()->create([
                'product_id' => $product->id,
                'state_code' => $state,
                'amount_cents' => $amountCents,
                'currency' => 'usd',
                'interval' => $interval,
                'floor_cents' => $floorCents,
                'starts_at' => $now,
                'created_by' => $actor->id,
            ]);

            $this->audit->record('price.changed', self::AUDIT_SUBJECT, $product->key, [
                'state' => $state,
                'interval' => $interval->value,
                'from' => $current?->amount_cents,
                'to' => $amountCents,
                'floor' => $floorCents,
            ], $actor);

            return $price;
        });
    }

    /** Stops selling a price; buyers keep what they bought. */
    public function endPrice(Price $price, User $actor): void
    {
        $price->forceFill(['ends_at' => now()])->save();
        $this->audit->record('price.ended', self::AUDIT_SUBJECT, $price->product->key, ['state' => $price->state_code, 'interval' => $price->interval->value, 'from' => $price->amount_cents, 'to' => null], $actor);
    }

    private function key(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $key = $base;
        $n = 2;

        while (Product::query()->where('key', $key)->exists()) {
            $key = $base.'-'.$n++;
        }

        return $key;
    }

    /**
     * Instructors' and mentors' shares of a school product's sales (B5.2b),
     * replacing the old ones. Shares are percents of what's left after the
     * platform fee; the school keeps the rest, so they add up to 100 at most.
     *
     * @param  list<array{user_id: int, role: string, percent: int}>  $splits
     */
    public function setSplits(Product $product, array $splits, User $actor): void
    {
        if ($product->seller_organisation_id === null && $splits !== []) {
            throw ValidationException::withMessages(['splits' => __('payouts.errors.noSeller')]);
        }

        if (array_sum(array_column($splits, 'percent')) > 100) {
            throw ValidationException::withMessages(['splits' => __('payouts.errors.over100')]);
        }

        $before = $product->splits()->orderBy('payee_user_id')->get(['payee_user_id', 'role', 'percent'])
            ->map(static fn (ProductSplit $s): array => ['user_id' => $s->payee_user_id, 'role' => $s->role->value, 'percent' => $s->percent])->values()->all();

        DB::transaction(function () use ($product, $splits): void {
            $product->splits()->delete();

            foreach ($splits as $split) {
                $product->splits()->create(['payee_user_id' => $split['user_id'], 'role' => $split['role'], 'percent' => $split['percent']]);
            }
        });

        usort($splits, static fn (array $a, array $b): int => $a['user_id'] <=> $b['user_id']);

        if ($before !== $splits) {
            $this->audit->record('product.splits_changed', self::AUDIT_SUBJECT, $product->key, ['before' => $before, 'after' => $splits], $actor);
        }
    }
}
