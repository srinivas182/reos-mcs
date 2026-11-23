<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** A student sent a question the course content couldn't answer (A10.1b, instructor screen i09). */
final class StudentAsked extends CentreNotification
{
    public function __construct(public readonly string $uuid, public readonly string $firstName, public readonly string $course, public readonly string $question) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::StudentQuestion;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('ask.instructor.mail.asked', ['name' => $this->firstName, 'course' => $this->course]);

        return ['title' => is_string($title) ? $title : '', 'body' => mb_strimwidth($this->question, 0, 160, '…'), 'url' => url('/instructor/questions?question='.$this->uuid)];
    }

    public function appPath(): string
    {
        return '/instructor/questions/'.$this->uuid;
    }

    public function collapseKey(): string
    {
        return 'student-question-'.$this->uuid;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('ask.instructor.mail.answer'), $inbox['url'] ?? url('/instructor/questions'));
    }
}
