<?php

declare(strict_types=1);

namespace App\Models;

use App\Billing\EntitlementSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * What a person may use (B5.1, ADR 0021): one feature key, from one source,
 * between two dates, in one state or every state. REOS keeps its own, fed
 * by every gateway's orders and subscriptions, sponsors and admins.
 *
 * @property int $id
 * @property int $user_id
 * @property string $feature
 * @property EntitlementSource $source
 * @property int|null $source_id
 * @property string|null $state_code
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Entitlement extends Model
{
    protected $fillable = ['user_id', 'feature', 'source', 'source_id', 'state_code', 'starts_at', 'ends_at', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'source' => EntitlementSource::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @param  Builder<static>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')
            ->where('starts_at', '<=', now())
            ->where(static fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
