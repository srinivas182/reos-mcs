<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;

/** The brokerage assigned a course (A16.2), with its due date. */
final class CourseAssigned extends CentreNotification
{
    public function __construct(public readonly string $course, public readonly string $slug, public readonly string $brokerage, public readonly ?string $dueOn) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::CourseAssigned;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('brokerage.ce.assign.notifyTitle', ['course' => $this->course]);
        $body = $this->dueOn === null
            ? __('brokerage.ce.assign.notifyBody', ['brokerage' => $this->brokerage])
            : __('brokerage.ce.assign.notifyDue', ['brokerage' => $this->brokerage, 'date' => self::longDate($this->dueOn)]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/learn?course='.$this->slug)];
    }

    public function appPath(): string
    {
        return '/learn';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('brokerage.ce.assign.open'), $inbox['url'] ?? url('/'));
    }

    private static function longDate(string $date): string
    {
        $parsed = CarbonImmutable::parse($date);
        $local = $parsed->locale(app()->getLocale());

        return ($local instanceof CarbonImmutable ? $local : $parsed)->isoFormat('LL');
    }
}
