<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** "Ana R. got their license: you earned 2,500 points" (B14.3b); opens Rewards. */
final class InviteRewarded extends CentreNotification
{
    public function __construct(public readonly string $name, public readonly int $points) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::InviteReward;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('leads.invites.notify.title', ['name' => $this->name, 'points' => number_format($this->points)]);
        $body = __('leads.invites.notify.body');

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/referrals')];
    }

    public function appPath(): string
    {
        return '/referrals';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('leads.invites.notify.open'), $inbox['url'] ?? url('/referrals'));
    }
}
