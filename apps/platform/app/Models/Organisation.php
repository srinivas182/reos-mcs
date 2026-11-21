<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganisationType;
use App\Tenancy\TenantStatus;
use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A brokerage, school, association, service business or REOS itself: a
 * tenant (A4.1, ADR 0015). Roles are held in an organisation (Spatie
 * "teams"); its data is isolated by row-level security.
 *
 * @property int $id
 * @property OrganisationType $type
 * @property string $name
 * @property string $slug
 * @property TenantStatus $status
 * @property list<string>|null $states
 * @property string|null $plan
 * @property string $region
 * @property string $cell
 * @property array<string, int>|null $limits
 * @property array<string, bool>|null $features
 * @property int|null $parent_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organisation|null $parent
 */
#[Fillable(['type', 'name', 'slug', 'status', 'states', 'plan', 'region', 'cell', 'limits', 'features', 'parent_id'])]
final class Organisation extends Model
{
    /** @use HasFactory<OrganisationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Every tenant gets a unique slug for `<slug>.reos.com`.
        self::creating(static function (self $organisation): void {
            if (($organisation->attributes['slug'] ?? null) === null) {
                $base = Str::limit(Str::slug($organisation->name) ?: 'tenant', 50, '');
                $slug = $base;

                while (self::query()->where('slug', $slug)->exists()) {
                    $slug = $base.'-'.Str::lower(Str::random(4));
                }

                $organisation->slug = $slug;
            }
        });
    }

    /** REOS itself, created by the roles migration. */
    public static function platform(): self
    {
        return self::query()->firstOrCreate(
            ['type' => OrganisationType::Platform, 'parent_id' => null],
            ['name' => 'REOS platform'],
        );
    }

    /** @return BelongsTo<Organisation, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Organisation, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => OrganisationType::class,
            'status' => TenantStatus::class,
            'states' => 'array',
            'limits' => 'array',
            'features' => 'array',
        ];
    }
}
