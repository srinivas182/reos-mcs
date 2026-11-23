<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An agent's 90-day plan for one goal (A11.1b).
 *
 * @property int $id
 * @property int $user_id
 * @property string $goal
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property CarbonImmutable|null $promised_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, AgentPlanTask> $tasks
 */
final class AgentPlan extends Model
{
    protected $fillable = ['user_id', 'goal', 'starts_on', 'ends_on', 'promised_at'];

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'promised_at' => 'immutable_datetime'];
    }

    /** @return HasMany<AgentPlanTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(AgentPlanTask::class);
    }
}
