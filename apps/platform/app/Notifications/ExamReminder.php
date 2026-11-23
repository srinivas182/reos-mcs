<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LicenceJourney;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The licence exam (A10.3a): a week before (`week`), the day before with the
 * exam-day kit (`day`), and after it to enter the result (`result`).
 */
final class ExamReminder extends CentreNotification
{
    public const WEEK = 'week';

    public const DAY = 'day';

    public const RESULT = 'result';

    public function __construct(public readonly LicenceJourney $journey, public readonly string $when) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::ExamReminder;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $exam = $this->journey->exam_on;
        $at = $exam === null ? null : CarbonImmutable::instance($exam)->locale(app()->getLocale());
        $date = $at instanceof CarbonImmutable ? $at->isoFormat('dddd, MMMM D') : '';
        $title = __("licence.mail.{$this->when}.title", ['date' => $date]);
        $body = __("licence.mail.{$this->when}.body", ['date' => $date, 'centre' => $this->journey->test_centre ?? '']);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url($this->appPath())];
    }

    public function appPath(): string
    {
        return match ($this->when) {
            self::DAY => '/licence-journey/exam-day',
            self::RESULT => '/licence-journey/result',
            default => '/licence-journey',
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('licence.mail.open'), $inbox['url'] ?? url('/licence-journey'));
    }
}
