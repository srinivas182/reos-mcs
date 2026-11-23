<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One change of an agent's goal (A11.1a): from setup, by hand, or a suggested switch.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $from_goal
 * @property string $to_goal
 * @property string $reason setup, manual or suggested
 * @property CarbonImmutable $created_at
 */
final class AgentGoalChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'from_goal', 'to_goal', 'reason'];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
