<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\State;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "REOS is now live in Louisiana" (A5.1), to everyone on that state's waitlist, the day it goes live. */
final class StateWentLive extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly State $state) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
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
