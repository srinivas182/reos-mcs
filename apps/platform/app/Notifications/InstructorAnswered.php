<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** The instructor answered the student's question (A10.1b); the answer is in their Ask conversation. */
final class InstructorAnswered extends CentreNotification
{
    public function __construct(public readonly ?string $thread, public readonly string $instructor, public readonly string $answer) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::InstructorAnswer;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('ask.instructor.mail.answered', ['name' => $this->instructor]);

        return ['title' => is_string($title) ? $title : '', 'body' => mb_strimwidth($this->answer, 0, 160, '…'), 'url' => url('/ask'.($this->thread === null ? '' : '?thread='.$this->thread))];
    }

    public function appPath(): string
    {
        return '/ask'.($this->thread === null ? '' : '?thread='.$this->thread);
    }

    public function collapseKey(): string
    {
        return 'instructor-answer-'.($this->thread ?? md5($this->answer));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('ask.instructor.mail.read'), $inbox['url'] ?? url('/ask'));
    }
}
