<?php

declare(strict_types=1);

namespace App\Notifications\Centre\Push;

use App\Models\PushDevice;
use App\Models\User;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\Deliveries;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Push to every phone the person is signed in on (B6.2b, ADR 0023). Chats
 * collapse per conversation so a burst shows as one notification; a token
 * Expo says is dead is removed at once, and the rest are checked against
 * the receipts later (`reos:push:receipts`).
 *
 * @phpstan-import-type PushMessage from PushSender
 */
final readonly class PushChannel
{
    /** Expo takes up to 100 messages per request. */
    private const BATCH = 100;

    public function __construct(private PushSenderFactory $senders) {}

    public function send(object $notifiable, CentreNotification $notification): void
    {
        if (! $notifiable instanceof User) {
            return;
        }

        $kind = $notification->kind();
        $devices = PushDevice::query()->where('user_id', $notifiable->id)->get();
        $sender = $devices->isEmpty() ? null : $this->senders->make();

        if ($sender === null) {
            // No phone signed in with notifications on, or push isn't set up.
            Deliveries::record($notifiable->id, $kind, NotificationKind::PUSH, 'skipped');

            return;
        }

        $push = $notification->push($notifiable);
        $badge = $notifiable->unreadNotifications()->count();
        $messages = $devices->map(fn (PushDevice $device): array => $this->message($device, $notification, $push, $badge))->values()->all();

        foreach (array_chunk($messages, self::BATCH) as $offset => $chunk) {
            $tickets = $sender->send($chunk);
            $this->keep(array_values($devices->slice($offset * self::BATCH, self::BATCH)->all()), $tickets);
        }

        Deliveries::record($notifiable->id, $kind, NotificationKind::PUSH, $notification->released ? 'released' : 'sent');
    }

    /**
     * @param  array{title: string, body: string|null, path: string}  $push
     * @return PushMessage
     */
    private function message(PushDevice $device, CentreNotification $notification, array $push, int $badge): array
    {
        $kind = $notification->kind();
        $message = [
            'to' => $device->token,
            'title' => Str::limit($push['title'], 100),
            'data' => ['path' => $push['path'], 'kind' => $kind->value, 'id' => $notification->id],
            'sound' => 'default',
            'badge' => $badge,
            'priority' => $kind->urgent() ? 'high' : 'default',
            'channelId' => $kind->group() === 'messages' ? 'messages' : 'alerts',
            'threadId' => $kind->group(),
            // Something happening now is no use tomorrow.
            'ttl' => $kind->urgent() ? 3_600 : 86_400,
        ];

        if ($push['body'] !== null && $push['body'] !== '') {
            $message['body'] = Str::limit($push['body'], 178);
        }

        $collapse = $notification->collapseKey();

        if ($collapse !== null) {
            $message['collapseId'] = Str::limit($collapse, 64, '');
        }

        return $message;
    }

    /**
     * Removes phones Expo says are gone; keeps the other tickets to check
     * their receipts.
     *
     * @param  list<PushDevice>  $devices
     * @param  list<array{status: string, id?: string, error?: string}>  $tickets
     */
    private function keep(array $devices, array $tickets): void
    {
        $now = now();

        foreach ($devices as $i => $device) {
            $ticket = $tickets[$i] ?? null;

            if ($ticket === null) {
                continue;
            }

            if (($ticket['error'] ?? null) === 'DeviceNotRegistered') {
                $device->delete();

                continue;
            }

            if ($ticket['status'] === 'ok' && isset($ticket['id'])) {
                DB::table('push_tickets')->insert(['push_device_id' => $device->id, 'ticket' => $ticket['id'], 'created_at' => $now]);
                $device->forceFill(['last_used_at' => $now])->save();
            }
        }
    }
}
