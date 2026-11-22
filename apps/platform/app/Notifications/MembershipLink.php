<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** "Email me the link" (B5.1b, decision 4): plans are on reos.com; the app unlocks as soon as you've bought. */
final class MembershipLink extends CentreNotification
{
    public function kind(): NotificationKind
    {
        return NotificationKind::MembershipLink;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('billing.membership.mail.subject');
        $body = __('billing.membership.mail.body');

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => route('billing.index')];
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
