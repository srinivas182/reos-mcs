<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganisationType;
use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A brokerage, school, association, service business or REOS itself. Roles
 * are held in an organisation (Spatie "teams"). Minimal for now; A4.1 adds
 * row-level security, the full hierarchy, branding and domains.
 *
 * @property int $id
 * @property OrganisationType $type
 * @property string $name
 * @property int|null $parent_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organisation|null $parent
 */
#[Fillable(['type', 'name', 'parent_id'])]
final class Organisation extends Model
{
    /** @use HasFactory<OrganisationFactory> */
    use HasFactory;

    /** REOS itself, created by the roles migration. */
    public static function platform(): self
    {
        return self::query()->firstOrCreate(
            ['type' => OrganisationType::Platform, 'parent_id' => null],
            ['name' => 'REOS'],
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
        return ['type' => OrganisationType::class];
    }
}
