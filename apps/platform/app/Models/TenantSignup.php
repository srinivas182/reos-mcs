<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganisationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A brokerage or school signing up on the web (A6.1b): who, what, which
 * plan, the order that pays for it and, once paid, the tenant.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property OrganisationType $type
 * @property string $name
 * @property list<string> $states
 * @property int $price_id
 * @property int|null $order_id
 * @property int|null $created_organisation_id
 * @property int|null $broker_verification_id The designated broker's TREC check (A15.1a)
 * @property string $status pending, completed or cancelled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Price $price
 * @property-read Organisation|null $organisation
 */
final class TenantSignup extends Model
{
    protected $fillable = ['uuid', 'user_id', 'type', 'name', 'states', 'price_id', 'order_id', 'created_organisation_id', 'status', 'broker_verification_id'];

    protected function casts(): array
    {
        return ['type' => OrganisationType::class, 'states' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Price, $this> */
    public function price(): BelongsTo
    {
        return $this->belongsTo(Price::class);
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'created_organisation_id');
    }
}
