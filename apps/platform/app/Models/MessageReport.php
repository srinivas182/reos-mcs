<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A report of messages (B6.1b): why, by whom, about whom, and a copy of the
 * messages as they were. Goes to the tenant's admins and REOS.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $conversation_id
 * @property int|null $organisation_id
 * @property int|null $reporter_id
 * @property int|null $reported_user_id
 * @property string $reason spam, harassment, inappropriate or other
 * @property int|null $resolved_by
 * @property string|null $details
 * @property list<array{id: string, sender: string|null, body: string|null, at: string}> $messages
 * @property string $status open or resolved
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $reporter
 * @property-read User|null $reported
 * @property-read Organisation|null $organisation
 */
final class MessageReport extends Model
{
    public const REASONS = ['spam', 'harassment', 'inappropriate', 'other'];

    protected $fillable = ['uuid', 'conversation_id', 'organisation_id', 'reporter_id', 'reported_user_id', 'reason', 'details', 'messages', 'status', 'resolved_at', 'resolved_by'];

    protected function casts(): array
    {
        return ['messages' => 'array', 'resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reported(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
