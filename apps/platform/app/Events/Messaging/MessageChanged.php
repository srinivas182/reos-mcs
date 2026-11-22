<?php

declare(strict_types=1);

namespace App\Events\Messaging;

use App\Messaging\MessagePresenter;
use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** A message edited or deleted for everyone (B6.1): `message.changed` on the conversation. */
final readonly class MessageChanged implements ShouldBroadcastNow
{
    public function __construct(public Message $message) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('conversations.'.$this->message->conversation->uuid);
    }

    public function broadcastAs(): string
    {
        return 'message.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['conversation' => $this->message->conversation->uuid, 'message' => MessagePresenter::message($this->message)];
    }
}
