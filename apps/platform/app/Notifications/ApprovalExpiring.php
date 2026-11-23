<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CourseApproval;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * "Principles of Real Estate II: the Texas approval expires in 30 days" (A7.1),
 * to the school's admins (super admins for REOS's own courses). With no days
 * left it says the approval expired and the course lost its label there.
 */
final class ApprovalExpiring extends CentreNotification
{
    public function __construct(public readonly CourseApproval $approval, public readonly int $daysLeft) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::ApprovalExpiring;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        return ['title' => $this->title(), 'body' => $this->body(), 'url' => $this->url()];
    }

    public function appPath(): string
    {
        return '/';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->body())
            ->action(self::text('approvals.mail.action'), $this->url())
            ->line(self::text('approvals.mail.next'));
    }

    private function title(): string
    {
        $values = ['course' => $this->approval->course->title, 'state' => $this->approval->state_code, 'days' => $this->daysLeft];

        return $this->daysLeft <= 0 ? self::text('approvals.mail.expiredSubject', $values) : self::text('approvals.mail.subject', $values);
    }

    private function body(): string
    {
        $values = ['course' => $this->approval->course->title, 'state' => $this->approval->state_code, 'number' => (string) $this->approval->number, 'date' => $this->approval->expires_on?->toFormattedDateString() ?? ''];

        return $this->daysLeft <= 0 ? self::text('approvals.mail.expiredBody', $values) : self::text('approvals.mail.body', $values);
    }

    private function url(): string
    {
        return $this->approval->course->organisation_id === null ? url('/admin/content/library') : url('/school/approvals');
    }

    /** @param  array<string, string|int>  $values */
    private static function text(string $key, array $values = []): string
    {
        $text = __($key, $values);

        return is_string($text) ? $text : $key;
    }
}
