<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the compliance log (B13.2): a check, fix, approval request,
 * approval or send-back on one channel.
 *
 * @property int $id
 * @property int|null $organisation_id
 * @property int $user_id the agent whose content it was
 * @property int|null $actor_id who did it (the agent, the broker)
 * @property string $channel post, video, page, email or text
 * @property string $action checked, blocked, fixed, sent_to_broker, approved or sent_back
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property list<array{code: string, phrase: string|null, citation: string}> $issues
 * @property string|null $note
 * @property CarbonImmutable $created_at
 * @property-read User $user
 * @property-read User|null $actor
 */
final class ComplianceEvent extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = ['checked', 'blocked', 'fixed', 'sent_to_broker', 'approved', 'sent_back'];

    protected $fillable = ['organisation_id', 'user_id', 'actor_id', 'channel', 'action', 'subject_type', 'subject_id', 'issues', 'note', 'created_at'];

    protected function casts(): array
    {
        return ['issues' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
