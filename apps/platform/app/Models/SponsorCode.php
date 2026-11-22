<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A code a sponsor shares so its people get a product's access (B5.1b;
 * candidate screen 6: "Is your school, brokerage or association paying?").
 * Each person can use a code once; it stops at its seats or expiry.
 *
 * @property int $id
 * @property string $code
 * @property int $sponsor_organisation_id
 * @property int $product_id
 * @property string $state_code
 * @property int $seats
 * @property int $used
 * @property int|null $access_months
 * @property Carbon|null $expires_at
 * @property bool $active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organisation $sponsor
 * @property-read Product $product
 * @property-read Collection<int, SponsorCodeRedemption> $redemptions
 */
final class SponsorCode extends Model
{
    protected $fillable = ['code', 'sponsor_organisation_id', 'product_id', 'state_code', 'seats', 'used', 'access_months', 'expires_at', 'active', 'created_by'];

    protected function casts(): array
    {
        return ['seats' => 'integer', 'used' => 'integer', 'access_months' => 'integer', 'expires_at' => 'datetime', 'active' => 'boolean'];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'sponsor_organisation_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<SponsorCodeRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(SponsorCodeRedemption::class);
    }
}
