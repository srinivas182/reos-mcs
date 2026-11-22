<?php

declare(strict_types=1);

namespace App\Models;

use App\Billing\Payouts\PayeeRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An instructor's or mentor's share of a school product's sales (B5.2b):
 * a percent of what's left after REOS's platform fee; the school keeps the rest.
 *
 * @property int $id
 * @property int $product_id
 * @property int $payee_user_id
 * @property PayeeRole $role
 * @property int $percent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $payee
 */
final class ProductSplit extends Model
{
    protected $fillable = ['product_id', 'payee_user_id', 'role', 'percent'];

    protected function casts(): array
    {
        return ['role' => PayeeRole::class, 'percent' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function payee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payee_user_id');
    }
}
