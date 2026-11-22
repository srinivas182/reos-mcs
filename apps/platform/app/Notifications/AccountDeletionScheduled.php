<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;

/** Confirms a deletion request and how to change one's mind. */
final class AccountDeletionScheduled extends CentreNotification
{
    public function __construct(public readonly CarbonInterface $deleteOn) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::AccountDeletion;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('identity.deletion.subject');
        $body = __('identity.deletion.body', ['date' => $this->deleteOn->toFormattedDayDateString()]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => null];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('identity.deletion.subject'))
            ->line(__('identity.deletion.body', ['date' => $this->deleteOn->toFormattedDayDateString()]))
            ->line(__('identity.deletion.changeMind'));
    }
}
