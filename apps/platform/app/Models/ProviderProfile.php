<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A provider's profile as they join (B15.1, ADR 0073): the category they
 * chose and their answers to its profile fields. B15.2 adds areas, plan and
 * network rules; B15.3 the public profile.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $provider_category_id
 * @property array<string, string|bool|list<string>>|null $fields
 * @property string $status joining, waiting, live or hidden (B15.2)
 * @property string|null $hidden_reason licence_expired, licence_inactive or licence_rejected
 * @property Carbon|null $welcomed_at
 * @property string|null $business_name
 * @property string|null $business_address
 * @property string $state_code
 * @property string|null $tier basic (Listed), pro or premium (Partner)
 * @property int|null $organisation_id The provider business, once the plan is bought
 * @property string|null $rules_version
 * @property Carbon|null $rules_accepted_at
 * @property Carbon|null $live_at
 * @property Carbon|null $checked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProviderCategory|null $category
 * @property-read User $user
 * @property-read Collection<int, ProviderServiceArea> $areas
 */
final class ProviderProfile extends Model
{
    public const JOINING = 'joining';

    /** Every step done, but the network isn't open in the state yet (decision 5). */
    public const WAITING = 'waiting';

    public const LIVE = 'live';

    public const HIDDEN = 'hidden';

    /** @var array<string, mixed> */
    protected $attributes = ['status' => self::JOINING, 'state_code' => 'TX'];

    protected $fillable = ['user_id', 'provider_category_id', 'fields', 'status', 'hidden_reason', 'welcomed_at', 'business_name', 'business_address', 'state_code', 'tier', 'organisation_id', 'rules_version', 'rules_accepted_at', 'live_at', 'checked_at'];

    protected function casts(): array
    {
        return ['fields' => 'array', 'welcomed_at' => 'datetime', 'rules_accepted_at' => 'datetime', 'live_at' => 'datetime', 'checked_at' => 'datetime'];
    }

    /** @return HasMany<ProviderServiceArea, $this> */
    public function areas(): HasMany
    {
        return $this->hasMany(ProviderServiceArea::class);
    }

    /** @return BelongsTo<ProviderCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProviderCategory::class, 'provider_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
