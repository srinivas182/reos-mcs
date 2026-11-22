<?php

declare(strict_types=1);

namespace App\Events\Video;

use App\Models\VideoSession;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * The session ended (B6.3): at the booked end or by the host. `session.ended`
 * on each participant's own channel, so every open room closes at once.
 */
final readonly class SessionEnded implements ShouldBroadcastNow
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
        return 'session.ended';
    }

    /** @return array{session: string, ended_at: string|null} */
    public function broadcastWith(): array
    {
        return ['session' => $this->session->uuid, 'ended_at' => $this->session->ended_at?->toIso8601String()];
    }
}
