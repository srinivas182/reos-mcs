<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A provider plan's flat monthly price in one state and tier (A6.1).
 *
 * @property int $id
 * @property int $provider_category_id
 * @property string $state_code
 * @property string $tier
 * @property int $monthly_cents
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class ProviderCategoryPrice extends Model
{
    protected $fillable = ['provider_category_id', 'state_code', 'tier', 'monthly_cents'];

    protected function casts(): array
    {
        return ['monthly_cents' => 'integer'];
    }
}
