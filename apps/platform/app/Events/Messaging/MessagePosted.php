<?php

declare(strict_types=1);

namespace App\Events\Messaging;

use App\Messaging\MessagePresenter;
use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * A new message, live to the conversation and each participant's inbox
 * (B6.1). `message.posted` on `conversations.{uuid}` and `users.{id}`.
 */
final readonly class MessagePosted implements ShouldBroadcastNow
{
    /** @param  list<int>  $recipients */
    public function __construct(public Message $message, public array $recipients) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversations.'.$this->message->conversation->uuid),
            ...array_map(static fn (int $id): PrivateChannel => new PrivateChannel("users.{$id}"), $this->recipients),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.posted';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['conversation' => $this->message->conversation->uuid, 'message' => MessagePresenter::message($this->message)];
    }
}
