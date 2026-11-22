<?php

declare(strict_types=1);

namespace App\Models;

use App\Features\RolloutStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A feature flag's rollout (A6.2, screen 70). The flag itself is declared in
 * `FlagCatalogue`; this row says who has it.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property string|null $owner
 * @property bool $tenant_feature
 * @property RolloutStage $stage
 * @property list<int>|null $tenant_ids
 * @property list<string>|null $states
 * @property int $percent
 * @property list<string>|null $roles
 * @property int $error_threshold errors per 100 uses that, for 10 minutes in a row, step the rollout back
 * @property Carbon|null $stage_changed_at
 * @property Carbon|null $killed_at
 * @property int|null $killed_by
 * @property Carbon|null $rolled_back_at
 * @property RolloutStage|null $rolled_back_from
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class FeatureFlag extends Model
{
    protected $fillable = ['key', 'name', 'description', 'owner', 'tenant_feature', 'stage', 'tenant_ids', 'states', 'percent', 'roles', 'error_threshold', 'stage_changed_at', 'killed_at', 'killed_by', 'rolled_back_at', 'rolled_back_from'];

    protected function casts(): array
    {
        return [
            'tenant_feature' => 'boolean',
            'stage' => RolloutStage::class,
            'rolled_back_from' => RolloutStage::class,
            'tenant_ids' => 'array',
            'states' => 'array',
            'roles' => 'array',
            'percent' => 'integer',
            'error_threshold' => 'integer',
            'stage_changed_at' => 'datetime',
            'killed_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    public function killed(): bool
    {
        return $this->killed_at !== null;
    }
}
