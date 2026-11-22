<?php

declare(strict_types=1);

namespace App\Models;

use App\Billing\PriceInterval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A product's price in one state (B5.1; decision 3). Never edited: a change
 * ends this price and starts a new one, so the history stays and people
 * who already subscribed keep their price until they renew.
 *
 * @property int $id
 * @property int $product_id
 * @property string $state_code
 * @property int $amount_cents
 * @property string $currency
 * @property PriceInterval $interval
 * @property int|null $floor_cents The least a sponsor discount may bring it to
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 */
final class Price extends Model
{
    protected $fillable = ['product_id', 'state_code', 'amount_cents', 'currency', 'interval', 'floor_cents', 'starts_at', 'ends_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'interval' => PriceInterval::class,
            'amount_cents' => 'integer',
            'floor_cents' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @param  Builder<static>  $query */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('starts_at', '<=', now())->where(static fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function isCurrent(): bool
    {
        return $this->starts_at->lte(now()) && ($this->ends_at === null || $this->ends_at->isFuture());
    }
}
