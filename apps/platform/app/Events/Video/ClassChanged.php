<?php

declare(strict_types=1);

namespace App\Events\Video;

use App\Models\VideoSession;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Something changed in a live class (A10.2b): a poll, a vote, a question,
 * a like, a hand, a chat message or an attendance check. `class.changed`
 * to everyone in the room, whose screens fetch the class again.
 */
final readonly class ClassChanged implements ShouldBroadcastNow
{
    /** @param  list<int>  $userIds */
    public function __construct(public VideoSession $session, public array $userIds, public string $what) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return array_map(static fn (int $id): PrivateChannel => new PrivateChannel('users.'.$id), $this->userIds);
    }

    public function broadcastAs(): string
    {
        return 'class.changed';
    }

    /** @return array{session: string, what: string} */
    public function broadcastWith(): array
    {
        return ['session' => $this->session->uuid, 'what' => $this->what];
    }
}
