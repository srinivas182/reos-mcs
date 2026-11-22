<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A brokerage, association or school pays part of a product for its people
 * (B5.1; screen 3: "sponsor discounts applied automatically"), never below
 * the price's floor.
 *
 * @property int $id
 * @property int $sponsor_organisation_id
 * @property int $product_id
 * @property int $amount_cents
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organisation $sponsor
 */
final class SponsorDiscount extends Model
{
    protected $fillable = ['sponsor_organisation_id', 'product_id', 'amount_cents', 'active'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'active' => 'boolean'];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'sponsor_organisation_id');
    }
}
