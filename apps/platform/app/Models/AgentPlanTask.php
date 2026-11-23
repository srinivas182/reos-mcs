<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * A plan task the agent ticked off (A11.1b).
 *
 * @property int $id
 * @property int $agent_plan_id
 * @property int $week
 * @property string $code
 * @property CarbonImmutable $done_at
 */
final class AgentPlanTask extends Model
{
    public $timestamps = false;

    protected $fillable = ['agent_plan_id', 'week', 'code', 'done_at'];

    protected function casts(): array
    {
        return ['week' => 'integer', 'done_at' => 'immutable_datetime'];
    }
}
