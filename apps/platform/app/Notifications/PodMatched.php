<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Pod;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** An agent is in a pod (A13.2): who's in it and when the weekly call is. */
final class PodMatched extends CentreNotification
{
    public function __construct(public readonly Pod $pod) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::Mentoring;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('pods.notify.matched', ['name' => $this->pod->name]);
        $body = __('pods.notify.matchedBody', ['day' => __("pods.days.{$this->pod->call_day}"), 'time' => $this->pod->call_time]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/pod')];
    }

    public function appPath(): string
    {
        return '/pod';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('mentoring.notify.open'), $inbox['url'] ?? url('/'));
    }
}
