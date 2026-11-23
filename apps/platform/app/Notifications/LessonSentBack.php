<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Lesson;
use App\Models\User;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** "Paul Diaz sent back Disclosing who you represent" (A8.1), to the lesson's author, with the reviewer's note. */
final class LessonSentBack extends CentreNotification
{
    public function __construct(public readonly Lesson $lesson, public readonly User $reviewer, public readonly string $note) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::ContentReview;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        return ['title' => $this->title(), 'body' => $this->note, 'url' => $this->url()];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title())->line($this->note)->action(self::text('review.mail.open'), $this->url());
    }

    private function url(): string
    {
        return url('/course-builder/lessons/'.$this->lesson->id.'/review');
    }

    private function title(): string
    {
        return self::text('review.mail.sentBack', ['name' => (string) $this->reviewer->name, 'lesson' => $this->lesson->title]);
    }

    /** @param  array<string, string>  $values */
    private static function text(string $key, array $values = []): string
    {
        $text = __($key, $values);

        return is_string($text) ? $text : $key;
    }
}
