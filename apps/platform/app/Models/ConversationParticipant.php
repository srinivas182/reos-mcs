<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Someone in a conversation (B6.1) and how far they've read.
 *
 * @property int $id
 * @property int $conversation_id
 * @property int $user_id
 * @property int|null $last_read_message_id
 * @property Carbon|null $last_read_at
 * @property Carbon $joined_at
 * @property Carbon|null $left_at
 * @property-read User $user
 * @property-read Conversation $conversation
 */
final class ConversationParticipant extends Model
{
    public $timestamps = false;

    protected $fillable = ['conversation_id', 'user_id', 'last_read_message_id', 'last_read_at', 'joined_at', 'left_at'];

    protected function casts(): array
    {
        return ['last_read_at' => 'datetime', 'joined_at' => 'datetime', 'left_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
