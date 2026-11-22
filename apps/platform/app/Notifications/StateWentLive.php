<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\State;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** "REOS is now live in Louisiana" (A5.1), to everyone on that state's waitlist, the day it goes live. */
final class StateWentLive extends CentreNotification
{
    public function __construct(public readonly State $state) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::StateWentLive;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('states.liveMail.subject', ['state' => $this->state->name]);

        return ['title' => is_string($title) ? $title : '', 'body' => null, 'url' => url('/get-started')];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('states.liveMail.subject', ['state' => $this->state->name]))
            ->line(__('states.liveMail.body', ['state' => $this->state->name]))
            ->action(__('states.liveMail.action'), url('/get-started'))
            ->line(__('states.liveMail.bonus'));
    }
}
