<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** "Maria Garcia referred a client to you" (B14.3b); opens Referrals to accept or decline. */
final class ReferralReceived extends CentreNotification
{
    public function __construct(public readonly string $referral, public readonly string $from, public readonly string $client) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::Referral;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('leads.referrals.notify.received', ['from' => $this->from]);
        $body = __('leads.referrals.notify.receivedBody', ['client' => $this->client]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/referrals?referral='.$this->referral)];
    }

    public function appPath(): string
    {
        return '/referrals';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('leads.referrals.notify.open'), $inbox['url'] ?? url('/referrals'));
    }
}
