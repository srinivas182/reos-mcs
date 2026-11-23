<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The morning brief is ready (A11.1c, screen 184). It says only that, so
 * nothing private shows on a lock screen; a tap opens the brief.
 */
final class MorningBriefReady extends CentreNotification
{
    public function kind(): NotificationKind
    {
        return NotificationKind::MorningBrief;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('agents.brief.ready');

        return ['title' => is_string($title) ? $title : '', 'body' => null, 'url' => url('/agent/brief')];
    }

    public function appPath(): string
    {
        return '/agent/brief';
    }

    /** One a day: today's replaces yesterday's. */
    public function collapseKey(): string
    {
        return 'morning-brief';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->action($inbox['title'], $inbox['url'] ?? url('/'));
    }
}
