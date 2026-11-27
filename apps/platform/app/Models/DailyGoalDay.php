<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One agent's day of goals (A13.3a): what was done, the points earned and
 * whether every goal was met (which keeps the streak going).
 *
 * @property int $id
 * @property int $user_id
 * @property string $day Y-m-d in the agent's time zone (text, so every database compares it the same way)
 * @property string $mode
 * @property list<array{key: string, done: float|int, target: int, met: bool}> $goals
 * @property int $met
 * @property int $total
 * @property int $points
 * @property int $bonus
 * @property bool $completed
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class DailyGoalDay extends Model
{
    protected $fillable = ['user_id', 'day', 'mode', 'goals', 'met', 'total', 'points', 'bonus', 'completed'];

    protected $attributes = ['met' => 0, 'total' => 0, 'points' => 0, 'bonus' => 0, 'completed' => false];

    protected function casts(): array
    {
        return ['goals' => 'array', 'met' => 'integer', 'total' => 'integer', 'points' => 'integer', 'bonus' => 'integer', 'completed' => 'boolean', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
