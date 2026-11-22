<?php

declare(strict_types=1);

namespace App\Models;

use App\Billing\Buyer;
use App\Billing\ProductKind;
use App\Billing\ProductStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Something REOS sells (B5.1, ADR 0021; screen 67): a plan, course, package,
 * credit pack or seat pack for one kind of buyer, with the features
 * (entitlement keys such as `learn.qualifying`) it unlocks.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property ProductKind $kind
 * @property Buyer $buyer
 * @property string|null $description
 * @property list<string>|null $includes What's included, in plain words
 * @property list<string>|null $features Entitlement keys
 * @property string|null $ai_help
 * @property int|null $credits
 * @property int|null $seats
 * @property ProductStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Price> $prices
 */
final class Product extends Model
{
    protected $fillable = ['key', 'name', 'kind', 'buyer', 'description', 'includes', 'features', 'ai_help', 'credits', 'seats', 'status'];

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'kind' => ProductKind::class,
            'buyer' => Buyer::class,
            'status' => ProductStatus::class,
            'includes' => 'array',
            'features' => 'array',
            'credits' => 'integer',
            'seats' => 'integer',
        ];
    }

    /** @return HasMany<Price, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class)->orderByDesc('starts_at')->orderByDesc('id');
    }
}
