<?php

declare(strict_types=1);

namespace App\Notifications\Centre;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Gathers an alert into the person's daily or weekly digest (B6.2) instead of sending it now. */
final readonly class DigestChannel
{
    public function __construct(private NotificationCentre $centre) {}

    public function send(object $notifiable, CentreNotification $notification): void
    {
        if (! $notifiable instanceof User) {
            return;
        }

        $kind = $notification->kind();
        $inbox = $notification->inbox($notifiable);

        DB::table('digest_items')->insert([
            'user_id' => $notifiable->id,
            'kind' => $kind->value,
            'frequency' => $this->centre->digest($notifiable, $kind) ?? 'daily',
            'title' => Str::limit($inbox['title'], 200, ''),
            'body' => $inbox['body'] === null ? null : Str::limit($inbox['body'], 500, ''),
            'url' => $inbox['url'] === null ? null : Str::limit($inbox['url'], 500, ''),
            'created_at' => now(),
        ]);
        Deliveries::record($notifiable->id, $kind, 'alerts', 'digested');
    }
}
