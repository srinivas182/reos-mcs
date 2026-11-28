<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CommunityEvent;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** A new expert session in a community the person is in (B17.2): RSVP from the link. */
final class CommunitySessionPosted extends CentreNotification
{
    public function __construct(public readonly CommunityEvent $event) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::Announcement;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('communities.notifyTitle', ['community' => $this->event->community->name]);
        $body = __('communities.notifyBody', ['title' => $this->event->title]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => route('communities.show', $this->event->community->uuid)];
    }

    public function appPath(): string
    {
        return '/communities/'.$this->event->community->uuid;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action($inbox['title'], $inbox['url'] ?? url('/'));
    }
}
