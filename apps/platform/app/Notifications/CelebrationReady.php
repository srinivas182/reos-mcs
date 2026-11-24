<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Celebration;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** A milestone is ready to post, free (B12.3, screen 163); at most one a day. */
final class CelebrationReady extends CentreNotification
{
    public function __construct(public readonly Celebration $celebration) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::Celebration;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __("studio.celebrate.notify.{$this->celebration->milestone}");
        $body = __('studio.celebrate.notify.body');

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/studio')];
    }

    public function appPath(): string
    {
        return '/social/celebrate/'.$this->celebration->uuid;
    }

    public function collapseKey(): string
    {
        return "celebration-{$this->celebration->uuid}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('studio.notify.open'), $inbox['url'] ?? url('/'));
    }
}
