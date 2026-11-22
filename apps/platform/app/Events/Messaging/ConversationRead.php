<?php

declare(strict_types=1);

namespace App\Events\Messaging;

use App\Models\ConversationParticipant;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** Someone read the conversation up to now (B6.1): `conversation.read`, for "Read" under messages. */
final readonly class ConversationRead implements ShouldBroadcastNow
{
    public function __construct(public ConversationParticipant $participant) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('conversations.'.$this->participant->conversation->uuid);
    }

    public function broadcastAs(): string
    {
        return 'conversation.read';
    }

    /** @return array{conversation: string, user: int, read_at: string|null} */
    public function broadcastWith(): array
    {
        return [
            'conversation' => $this->participant->conversation->uuid,
            'user' => $this->participant->user_id,
            'read_at' => $this->participant->last_read_at?->toIso8601String(),
        ];
    }
}
