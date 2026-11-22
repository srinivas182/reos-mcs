<?php

declare(strict_types=1);

namespace App\Events\Video;

use App\Models\VideoSession;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Recording was asked for, answered, started, stopped or finished (B6.3c):
 * `session.recording`, so every open room shows the question or the
 * "Recording" sign at once.
 */
final readonly class SessionRecordingChanged implements ShouldBroadcastNow
{
    /** @param  list<int>  $userIds */
    public function __construct(public VideoSession $session, public array $userIds, public string $status) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return array_map(static fn (int $id): PrivateChannel => new PrivateChannel('users.'.$id), $this->userIds);
    }

    public function broadcastAs(): string
    {
        return 'session.recording';
    }

    /** @return array{session: string, status: string} */
    public function broadcastWith(): array
    {
        return ['session' => $this->session->uuid, 'status' => $this->status];
    }
}
