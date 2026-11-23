<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ClassCheck;
use App\Models\User;
use App\Models\VideoSession;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** The one reminder for an unanswered attendance check in a live class (A10.2b): urgent, it's happening now. */
final class ClassCheckReminder extends CentreNotification
{
    public function __construct(public readonly VideoSession $session, public readonly ClassCheck $check) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::ClassCheck;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('classes.mail.check.title', ['title' => $this->session->title]);
        $body = __('classes.mail.check.body', ['minutes' => max(1, (int) ceil(now()->diffInSeconds($this->check->closes_at, false) / 60))]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/sessions/'.$this->session->uuid)];
    }

    public function appPath(): string
    {
        return '/sessions/'.$this->session->uuid;
    }

    /** Only while the check is still open and unanswered. */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        $id = $notifiable instanceof User ? $notifiable->id : 0;

        return $this->check->closes_at->isFuture() && ! $this->check->answers()->where('user_id', $id)->exists();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('sessions.mail.join'), url('/sessions/'.$this->session->uuid));
    }
}
