<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An agent's goal (mode) and work-style flags (A11.1a), and when the
 * morning brief goes out (A11.1c).
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $goal launch, grow, lead or own
 * @property list<string> $flags
 * @property CarbonImmutable|null $welcomed_at
 * @property string|null $snoozed_goal
 * @property CarbonImmutable|null $snoozed_until
 * @property bool $brief_enabled the morning brief (A11.1c)
 * @property string $brief_at local time, HH:MM
 * @property string $brief_timezone
 * @property list<string>|null $brief_sections null for all
 * @property CarbonImmutable|null $brief_sent_on
 * @property CarbonImmutable|null $assistant_context_at when the agent let the assistant read their CRM, deals and calendar (A12.3)
 * @property string|null $photo_path the profile photo for designs (B12.3, screen 162), on the content disk
 * @property array{width: int, height: int, brightness: int, issues: list<string>}|null $photo_check
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $user
 */
final class AgentProfile extends Model
{
    protected $fillable = ['user_id', 'goal', 'flags', 'welcomed_at', 'snoozed_goal', 'snoozed_until', 'brief_enabled', 'brief_at', 'brief_timezone', 'brief_sections', 'brief_sent_on', 'assistant_context_at', 'photo_path', 'photo_check'];

    protected $attributes = ['flags' => '[]'];

    protected function casts(): array
    {
        return ['flags' => 'array', 'welcomed_at' => 'immutable_datetime', 'snoozed_until' => 'immutable_datetime', 'brief_enabled' => 'boolean', 'brief_sections' => 'array', 'brief_sent_on' => 'immutable_date', 'assistant_context_at' => 'immutable_datetime', 'photo_check' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
