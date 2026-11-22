<?php

declare(strict_types=1);

namespace App\Models;

use App\Billing\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One checkout (B5.1; screen 3): what was bought, at what price, with which
 * sponsor discount and tax, through which gateway, and whether it's paid.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $user_id Null once the account is deleted (kept for tax records)
 * @property int $product_id
 * @property int $price_id
 * @property string $state_code
 * @property int $amount_cents
 * @property int $discount_cents
 * @property int|null $sponsor_organisation_id
 * @property int|null $tax_cents
 * @property int|null $total_cents
 * @property string $currency
 * @property OrderStatus $status
 * @property string $gateway
 * @property string $environment
 * @property string|null $gateway_session_id
 * @property string|null $gateway_reference The gateway's own order or invoice number
 * @property string|null $postal_code For tax at gateways without built-in tax
 * @property string|null $tax_calculation_id
 * @property string|null $gateway_payment_id
 * @property string|null $gateway_subscription_id
 * @property string|null $receipt_url
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Product $product
 * @property-read Price $price
 * @property-read Organisation|null $sponsor
 */
final class Order extends Model
{
    protected $fillable = [
        'uuid', 'user_id', 'product_id', 'price_id', 'state_code', 'amount_cents', 'discount_cents', 'sponsor_organisation_id', 'tax_cents',
        'total_cents', 'currency', 'status', 'gateway', 'environment', 'gateway_session_id', 'gateway_reference', 'postal_code', 'tax_calculation_id', 'gateway_payment_id', 'gateway_subscription_id',
        'receipt_url', 'paid_at',
    ];

    protected $attributes = ['status' => 'pending', 'discount_cents' => 0, 'currency' => 'usd'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'amount_cents' => 'integer',
            'discount_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** What the buyer pays before tax. */
    public function chargeCents(): int
    {
        return max(0, $this->amount_cents - $this->discount_cents);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /** @return BelongsTo<Organisation, $this> */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'sponsor_organisation_id');
    }
}
