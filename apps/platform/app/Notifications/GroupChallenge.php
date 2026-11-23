<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** A study-group member challenged the group (A9.3c, screen 52): first name, the group, Accept. */
final class GroupChallenge extends CentreNotification
{
    public function __construct(public readonly string $uuid, public readonly string $firstName, public readonly string $group) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::Challenge;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('practice.groups.mail.title', ['name' => $this->firstName, 'group' => $this->group]);
        $body = __('practice.groups.mail.body');

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url($this->appPath())];
    }

    public function appPath(): string
    {
        return '/challenges/'.$this->uuid;
    }

    public function collapseKey(): string
    {
        return 'challenge-'.$this->uuid;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('practice.challenges.accept'), url($this->appPath()));
    }
}
