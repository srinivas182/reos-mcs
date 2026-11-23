<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Course;
use App\Models\User;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * "Dr. Rachel Hughes asks to change Principles of Real Estate II to 32 hours"
 * (A7.2), to the school's admins: seat time on an approved course is the
 * regulator's, so the school files the change with TREC and records it.
 */
final class SeatTimeChangeAsked extends CentreNotification
{
    public function __construct(public readonly Course $course, public readonly User $asker, public readonly float $hours, public readonly string $reason) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::ApprovalExpiring;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        return ['title' => $this->title(), 'body' => $this->reason, 'url' => url('/school/approvals')];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title())->line($this->reason)
            ->line(self::text('builder.seatTime.mailNext'))
            ->action(self::text('approvals.mail.action'), url('/school/approvals'));
    }

    private function title(): string
    {
        return self::text('builder.seatTime.mailSubject', ['name' => (string) $this->asker->name, 'course' => $this->course->title, 'hours' => rtrim(rtrim(number_format($this->hours, 1), '0'), '.')]);
    }

    /** @param  array<string, string>  $values */
    private static function text(string $key, array $values = []): string
    {
        $text = __($key, $values);

        return is_string($text) ? $text : $key;
    }
}
