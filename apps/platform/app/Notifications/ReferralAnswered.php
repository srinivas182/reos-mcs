<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** The agent you referred a client to accepted or declined (B14.3b). */
final class ReferralAnswered extends CentreNotification
{
    public function __construct(public readonly string $referral, public readonly string $agent, public readonly string $client, public readonly bool $accepted) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::Referral;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __($this->accepted ? 'leads.referrals.notify.accepted' : 'leads.referrals.notify.declined', ['agent' => $this->agent, 'client' => $this->client]);

        return ['title' => is_string($title) ? $title : '', 'body' => null, 'url' => url('/referrals?referral='.$this->referral)];
    }

    public function appPath(): string
    {
        return '/referrals';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->action(__('leads.referrals.notify.open'), $inbox['url'] ?? url('/referrals'));
    }
}
