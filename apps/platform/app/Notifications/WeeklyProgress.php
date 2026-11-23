<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** The weekly progress report (A9.2e, screen 55), Sunday evening: the week at a glance. */
final class WeeklyProgress extends CentreNotification
{
    /** @param  array{week_start: string, minutes: int, questions: int, readiness: array{now: int|null, change: int|null}, streak: int}  $report */
    public function __construct(public readonly array $report) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::WeeklyReport;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('practice.reports.mail.title');
        $body = __('practice.reports.mail.body', [
            'minutes' => (string) $this->report['minutes'], 'questions' => (string) $this->report['questions'], 'streak' => (string) $this->report['streak'],
        ]);
        $change = $this->report['readiness']['change'];
        $readiness = $this->report['readiness']['now'] === null ? null : __($change === null ? 'practice.reports.mail.readiness' : 'practice.reports.mail.readinessChange', [
            'percent' => (string) $this->report['readiness']['now'], 'change' => ($change !== null && $change > 0 ? '+' : '').$change,
        ]);

        return ['title' => is_string($title) ? $title : '', 'body' => (is_string($body) ? $body : '').(is_string($readiness) ? ' '.$readiness : ''), 'url' => url($this->appPath())];
    }

    public function appPath(): string
    {
        return '/practice/progress?week='.$this->report['week_start'];
    }

    public function collapseKey(): string
    {
        return 'weekly-report';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('practice.reports.mail.open'), url($this->appPath()));
    }
}
