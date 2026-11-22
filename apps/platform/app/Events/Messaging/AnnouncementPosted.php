<?php

declare(strict_types=1);

namespace App\Events\Messaging;

use App\Models\Announcement;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** A new announcement, live to each recipient's channel (B6.1b): `announcement.posted` on `users.{id}`. */
final readonly class AnnouncementPosted implements ShouldBroadcastNow
{
    /** @param  list<int>  $recipients */
    public function __construct(public Announcement $announcement, public array $recipients) {}

    /** @return list<PrivateChannel> */
    public function broadcastOn(): array
    {
        return array_map(static fn (int $id): PrivateChannel => new PrivateChannel("users.{$id}"), $this->recipients);
    }

    public function broadcastAs(): string
    {
        return 'announcement.posted';
    }

    /** @return array{id: string, title: string} */
    public function broadcastWith(): array
    {
        return ['id' => $this->announcement->uuid, 'title' => $this->announcement->title];
    }
}
