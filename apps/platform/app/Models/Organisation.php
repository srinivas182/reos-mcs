<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganisationType;
use App\Features\Flags;
use App\Tenancy\TenantFeature;
use App\Tenancy\TenantLimits;
use App\Tenancy\TenantPlan;
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
 * @property Carbon|null $trial_ends_at
 * @property string|null $status_reason
 * @property list<string>|null $states
 * @property TenantPlan|null $plan
 * @property string $region
 * @property string $cell
 * @property array<string, mixed>|null $limits Overrides of TenantLimits (see tenantLimits())
 * @property array<string, bool>|null $features
 * @property array{approval_new_agents?: bool, approval_all?: bool, banned?: list<string>}|null $advertising The brokerage's advertising policy (B12.1)
 * @property array{answerer_id?: int|null, reply_hours?: int}|null $virtual_broker Who answers virtual broker escalations and how fast (A12.1)
 * @property array<string, string|null>|null $brand Logo, colour, app label and email sender (see App\Tenancy\Brand)
 * @property string|null $trec_number The TREC brokerage licence a brokerage tenant stands for (A15.1a)
 * @property int|null $parent_id
 * @property int|null $session_max_30_cents Most its instructors may charge for 30 minutes (A10.2d)
 * @property int|null $session_max_60_cents Most its instructors may charge for 60 minutes (A10.2d)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organisation|null $parent
 */
#[Fillable(['type', 'name', 'slug', 'status', 'trial_ends_at', 'status_reason', 'states', 'plan', 'region', 'cell', 'limits', 'features', 'brand', 'parent_id', 'virtual_broker', 'advertising'])]
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

        // Flag answers are stored per tenant (A6.2): a change to what they depend on forgets them.
        self::updated(static function (self $organisation): void {
            if ($organisation->wasChanged(['features', 'states', 'type'])) {
                app(Flags::class)->tenantChanged();
            }
        });
    }

    /** Limits and data, with the defaults for its type filled in (screen 60). */
    public function tenantLimits(): TenantLimits
    {
        return TenantLimits::for($this->type, $this->limits);
    }

    /**
     * Whether a feature is on for this tenant (screen 60, A6.2): its own
     * choice, else the default for its type once the feature's rollout
     * reaches it; never while the feature is turned off everywhere.
     */
    public function hasFeature(TenantFeature $feature): bool
    {
        return app(Flags::class)->forTenant($this, $feature->value);
    }

    public function effectivePlan(): TenantPlan
    {
        return $this->plan ?? TenantPlan::defaultFor($this->type);
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
            'trial_ends_at' => 'datetime',
            'plan' => TenantPlan::class,
            'states' => 'array',
            'limits' => 'array',
            'features' => 'array',
            'brand' => 'array',
            'virtual_broker' => 'array',
            'advertising' => 'array',
        ];
    }
}
