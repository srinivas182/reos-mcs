<?php

declare(strict_types=1);

namespace App\Notifications\Centre;

use App\Models\User;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;

/** What went where (B6.2): kind, channel and outcome, never the words. */
final class Deliveries
{
    private const CHANNELS = ['database' => 'inbox', 'mail' => 'email'];

    public static function record(?int $userId, NotificationKind $kind, string $channel, string $status): void
    {
        DB::table('notification_deliveries')->insert([
            'user_id' => $userId, 'kind' => $kind->value, 'channel' => $channel, 'status' => $status, 'created_at' => now(),
        ]);
    }

    public static function sent(NotificationSent $event): void
    {
        self::fromEvent($event->notifiable, $event->notification, $event->channel, 'sent');
    }

    public static function failed(NotificationFailed $event): void
    {
        self::fromEvent($event->notifiable, $event->notification, $event->channel, 'failed');
    }

    private static function fromEvent(mixed $notifiable, mixed $notification, string $channel, string $status): void
    {
        if (! $notification instanceof CentreNotification || ! isset(self::CHANNELS[$channel])) {
            return;
        }

        self::record($notifiable instanceof User ? $notifiable->id : null, $notification->kind(), self::CHANNELS[$channel], $notification->released ? 'released' : $status);
    }
}
