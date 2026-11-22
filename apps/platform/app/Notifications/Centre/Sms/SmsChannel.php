<?php

declare(strict_types=1);

namespace App\Notifications\Centre\Sms;

use App\Messaging\TextMessagesFactory;
use App\Models\User;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\Deliveries;
use App\Notifications\Centre\NotificationKind;

/**
 * Texts (B6.2c, ADR 0023): only to a verified number with the person's SMS
 * consent on record, checked again at the moment of sending (a STOP may
 * have arrived while it waited for the morning).
 */
final readonly class SmsChannel
{
    public function __construct(private TextAlerts $alerts, private TextMessagesFactory $texts) {}

    public function send(object $notifiable, CentreNotification $notification): void
    {
        if (! $notifiable instanceof User) {
            return;
        }

        $kind = $notification->kind();
        $sender = $this->alerts->ready($notifiable) ? $this->texts->make() : null;

        if ($sender === null || $notifiable->phone === null) {
            Deliveries::record($notifiable->id, $kind, NotificationKind::SMS, 'skipped');

            return;
        }

        $sender->send($notifiable->phone, $notification->sms($notifiable));
        Deliveries::record($notifiable->id, $kind, NotificationKind::SMS, $notification->released ? 'released' : 'sent');
    }
}
