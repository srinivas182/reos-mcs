<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An accountability pod: up to five agents with the same goal, a weekly 15-minute call (A13.2, screen 131).
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property int|null $organisation_id the brokerage (null: independent agents)
 * @property string $state_code
 * @property string $goal
 * @property string $language
 * @property bool $part_time
 * @property string $call_day mon … sun
 * @property string $call_time HH:MM
 * @property string $timezone
 * @property int|null $conversation_id the pod's messages
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, PodMember> $members
 */
final class Pod extends Model
{
    public const SIZE = 5;

    /** Mondays at 8:30 am, or 6:30 pm for part-time agents (set when the pod is made). */
    protected $attributes = ['call_day' => 'mon', 'call_time' => '08:30', 'timezone' => 'America/Chicago', 'part_time' => false];

    protected $fillable = ['uuid', 'name', 'organisation_id', 'state_code', 'goal', 'language', 'part_time', 'call_day', 'call_time', 'timezone', 'conversation_id'];

    protected function casts(): array
    {
        return ['part_time' => 'boolean', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return HasMany<PodMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(PodMember::class)->whereNull('left_at');
    }
}
