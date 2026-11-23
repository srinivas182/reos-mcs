<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** Someone finished a challenge you took part in (A9.3b, screen 52): their first name and points. */
final class ChallengeFinished extends CentreNotification
{
    public function __construct(public readonly string $uuid, public readonly string $firstName, public readonly int $points, public readonly int $correct, public readonly int $total) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::Challenge;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('practice.challenges.mail.finished', ['name' => $this->firstName]);
        $body = __('practice.challenges.mail.score', ['points' => (string) $this->points, 'correct' => (string) $this->correct, 'total' => (string) $this->total]);

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

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('practice.challenges.mail.open'), url($this->appPath()));
    }
}
