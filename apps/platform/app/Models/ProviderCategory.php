<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A service provider category (A6.1, screen 68): what REOS checks before a
 * provider goes live, their profile fields, special permissions, and flat
 * monthly plan prices per state (RESPA: never per lead, referral or deal).
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property string|null $licence_check How the admin describes the check, such as "NMLS"
 * @property string|null $licence_kind The licence step in onboarding (B15.1): nmls, trec_inspector, tdi, or null for business details and an ID
 * @property string|null $verification
 * @property list<array{key: string, label: string, type: 'text'|'list'|'yes_no', options: list<string>}>|null $profile_fields
 * @property string|null $special_permissions
 * @property int $areas_on_pro
 * @property string $status live, draft, requested or retired
 * @property int $requests
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ProviderCategoryPrice> $prices
 * @property-read Collection<int, ProviderCategoryRequest> $requestNotes
 * @property-read int|null $profiles_count
 */
final class ProviderCategory extends Model
{
    public const STATUSES = ['live', 'draft', 'requested', 'retired'];

    /** Profile field types (B15.1): free text, one or more values, or yes/no. */
    public const FIELD_TYPES = ['text', 'list', 'yes_no'];

    /** The licence a category's providers show in onboarding (B15.1); none means business details and an ID. */
    public const LICENCE_KINDS = ['nmls', 'trec_inspector', 'tdi'];

    /** Plan tiers, cheapest first. */
    public const TIERS = ['basic', 'pro', 'premium'];

    protected $fillable = ['key', 'name', 'description', 'licence_check', 'licence_kind', 'verification', 'profile_fields', 'special_permissions', 'areas_on_pro', 'status', 'requests', 'updated_by'];

    protected function casts(): array
    {
        return ['profile_fields' => 'array', 'areas_on_pro' => 'integer', 'requests' => 'integer'];
    }

    /** @return HasMany<ProviderCategoryPrice, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(ProviderCategoryPrice::class);
    }

    /** @return HasMany<ProviderCategoryRequest, $this> who asked for it (B15.1) */
    public function requestNotes(): HasMany
    {
        return $this->hasMany(ProviderCategoryRequest::class);
    }

    /** @return HasMany<ProviderProfile, $this> providers who chose it */
    public function profiles(): HasMany
    {
        return $this->hasMany(ProviderProfile::class);
    }
}
