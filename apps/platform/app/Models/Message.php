<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One message (B6.1): text, a reply to another, edited or deleted for
 * everyone within 5 minutes of sending.
 *
 * @property int $id
 * @property string $uuid
 * @property int $conversation_id
 * @property int|null $user_id
 * @property string|null $body
 * @property int|null $reply_to_id
 * @property Carbon|null $edited_at
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property-read User|null $sender
 * @property-read Message|null $replyTo
 * @property-read Conversation $conversation
 */
final class Message extends Model
{
    public const UPDATED_AT = null;

    /** How long the sender can still edit or delete for everyone. */
    public const CHANGE_MINUTES = 5;

    public const MAX_LENGTH = 4000;

    protected $fillable = ['uuid', 'conversation_id', 'user_id', 'body', 'reply_to_id', 'edited_at', 'deleted_at'];

    protected function casts(): array
    {
        return ['edited_at' => 'datetime', 'deleted_at' => 'datetime', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Message, $this> */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function changeable(): bool
    {
        return $this->deleted_at === null && $this->created_at->greaterThan(now()->subMinutes(self::CHANGE_MINUTES));
    }
}
