<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\MentorAssignment;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** An agent asked for another mentor (A13.1a); the brokers are told. */
final class MentorChangeRequested extends CentreNotification
{
    public function __construct(public readonly MentorAssignment $assignment) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::Mentoring;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('mentoring.notify.change', ['name' => (string) $this->assignment->agent->name]);
        $body = __('mentoring.notify.changeBody', ['mentor' => (string) $this->assignment->mentor->name]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/organisation/mentors')];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('mentoring.notify.open'), $inbox['url'] ?? url('/'));
    }
}
