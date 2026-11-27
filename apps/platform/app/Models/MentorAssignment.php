<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An agent's assigned mentor at their brokerage, free to the agent (A13.1a, broker screen 23).
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $mentor_id
 * @property int $agent_id
 * @property int|null $assigned_by
 * @property list<string> $reasons language, focus, mode, capacity
 * @property CarbonImmutable|null $change_requested_at
 * @property CarbonImmutable|null $ended_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $mentor
 * @property-read User $agent
 */
final class MentorAssignment extends Model
{
    protected $fillable = ['organisation_id', 'mentor_id', 'agent_id', 'assigned_by', 'reasons', 'change_requested_at', 'ended_at'];

    protected function casts(): array
    {
        return ['reasons' => 'array', 'change_requested_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
