<?php

declare(strict_types=1);

namespace App\Events\Video;

use App\Models\VideoSession;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** The host shared a lesson or practice questions (B6.3b): `session.shared`, so open rooms show it at once. */
final readonly class SessionShared implements ShouldBroadcastNow
{
    /** @param  list<int>  $userIds */
    public function __construct(public VideoSession $session, public array $userIds) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return array_map(static fn (int $id): PrivateChannel => new PrivateChannel('users.'.$id), $this->userIds);
    }

    public function broadcastAs(): string
    {
        return 'session.shared';
    }

    /** @return array{session: string} */
    public function broadcastWith(): array
    {
        return ['session' => $this->session->uuid];
    }
}
