<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The broker's morning brief is ready (A16.3). Like the agent's, it says
 * only that, so nothing about an agent shows on a lock screen.
 */
final class BrokerBriefReady extends CentreNotification
{
    public function kind(): NotificationKind
    {
        return NotificationKind::MorningBrief;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('brokerage.brief.ready');

        return ['title' => is_string($title) ? $title : '', 'body' => null, 'url' => url('/dashboard')];
    }

    public function appPath(): string
    {
        return '/brokerage/brief';
    }

    /** One a day: today's replaces yesterday's. */
    public function collapseKey(): string
    {
        return 'broker-brief';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->action($inbox['title'], $inbox['url'] ?? url('/'));
    }
}
