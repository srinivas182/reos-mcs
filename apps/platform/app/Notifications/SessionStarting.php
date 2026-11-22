<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\VideoSession;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** "Starting in 10 minutes" (B6.3): urgent, so it arrives in quiet hours too. */
final class SessionStarting extends CentreNotification
{
    public function __construct(public readonly VideoSession $session) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::SessionStarting;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('sessions.mail.starting.title', ['title' => $this->session->title]);
        $body = __('sessions.mail.starting.body', ['when' => SessionBooked::when($this->session, $notifiable)]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/sessions/'.$this->session->uuid)];
    }

    public function appPath(): string
    {
        return '/sessions/'.$this->session->uuid;
    }

    /** Still worth sending only if it hasn't been cancelled or ended since. */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return in_array($this->session->fresh()?->status, [VideoSession::SCHEDULED, VideoSession::LIVE], true);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('sessions.mail.join'), url('/sessions/'.$this->session->uuid));
    }
}
