<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * An agent's goal (mode) and work-style flags (A11.1a).
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $goal launch, grow, lead or own
 * @property list<string> $flags
 * @property CarbonImmutable|null $welcomed_at
 * @property string|null $snoozed_goal
 * @property CarbonImmutable|null $snoozed_until
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
final class AgentProfile extends Model
{
    protected $fillable = ['user_id', 'goal', 'flags', 'welcomed_at', 'snoozed_goal', 'snoozed_until'];

    protected $attributes = ['flags' => '[]'];

    protected function casts(): array
    {
        return ['flags' => 'array', 'welcomed_at' => 'immutable_datetime', 'snoozed_until' => 'immutable_datetime'];
    }
}
