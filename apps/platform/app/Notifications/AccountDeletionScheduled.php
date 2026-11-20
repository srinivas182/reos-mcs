<?php

declare(strict_types=1);

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Confirms a deletion request and how to change one's mind. */
final class AccountDeletionScheduled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly CarbonInterface $deleteOn) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('identity.deletion.subject'))
            ->line(__('identity.deletion.body', ['date' => $this->deleteOn->toFormattedDayDateString()]))
            ->line(__('identity.deletion.changeMind'));
    }
}
