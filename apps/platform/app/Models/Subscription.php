<?php

declare(strict_types=1);

namespace App\Models;

use App\Billing\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A monthly or yearly plan at a gateway (B5.1). Its price stays as bought
 * until renewal; a failed renewal keeps access for the 7-day grace period.
 *
 * @property int $id
 * @property int $user_id
 * @property int $product_id
 * @property int $price_id
 * @property int|null $order_id
 * @property string $gateway
 * @property string $gateway_subscription_id
 * @property SubscriptionStatus $status
 * @property Carbon|null $current_period_end
 * @property Carbon|null $grace_ends_at
 * @property bool $cancel_at_period_end
 * @property Carbon|null $canceled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read Price $price
 * @property-read User $user
 */
final class Subscription extends Model
{
    protected $fillable = ['user_id', 'product_id', 'price_id', 'order_id', 'gateway', 'gateway_subscription_id', 'status', 'current_period_end', 'grace_ends_at', 'cancel_at_period_end', 'canceled_at'];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'current_period_end' => 'datetime',
            'grace_ends_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'canceled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Price, $this> */
    public function price(): BelongsTo
    {
        return $this->belongsTo(Price::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
