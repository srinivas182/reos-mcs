<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Email me the link" (B5.1b, decision 4): plans are on reos.com; the app unlocks as soon as you've bought. */
final class MembershipLink extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('billing.membership.mail.subject'))
            ->line(__('billing.membership.mail.body'))
            ->action(__('billing.membership.mail.action'), route('billing.index'))
            ->line(__('billing.membership.mail.unlock'));
    }
}
