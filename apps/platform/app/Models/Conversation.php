<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A conversation (B6.1): one to one or a small group (up to 20), between
 * people with a working relationship. Only its participants can read it.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $organisation_id
 * @property string $kind direct or group
 * @property string|null $title
 * @property int|null $created_by
 * @property Carbon|null $last_message_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ConversationParticipant> $participants
 * @property-read Collection<int, Message> $messages
 */
final class Conversation extends Model
{
    public const DIRECT = 'direct';

    public const GROUP = 'group';

    /** The most people in one conversation, the starter included. */
    public const MAX_PEOPLE = 20;

    protected $fillable = ['uuid', 'organisation_id', 'kind', 'title', 'created_by', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    /** @return HasMany<ConversationParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
