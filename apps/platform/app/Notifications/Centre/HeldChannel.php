<?php

declare(strict_types=1);

namespace App\Notifications\Centre;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Keeps an alert until quiet hours end (B6.2); `reos:notifications:release` sends it then. */
final readonly class HeldChannel
{
    public function __construct(private NotificationCentre $centre) {}

    public function send(object $notifiable, CentreNotification $notification): void
    {
        if (! $notifiable instanceof User) {
            return;
        }

        $kind = $notification->kind();
        $channels = $this->centre->alertChannels($notifiable, $kind);

        DB::table('held_notifications')->insert([
            'user_id' => $notifiable->id,
            'kind' => $kind->value,
            'channels' => json_encode($channels, JSON_THROW_ON_ERROR),
            'notification' => serialize($notification),
            'release_at' => $this->centre->quietEnds($notifiable),
            'created_at' => now(),
        ]);
        Deliveries::record($notifiable->id, $kind, 'alerts', 'held');
    }
}
