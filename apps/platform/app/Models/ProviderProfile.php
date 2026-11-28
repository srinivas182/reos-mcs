<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProviderCategory|null $category
 * @property-read User $user
 */
final class ProviderProfile extends Model
{
    protected $fillable = ['user_id', 'provider_category_id', 'fields'];

    protected function casts(): array
    {
        return ['fields' => 'array'];
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
