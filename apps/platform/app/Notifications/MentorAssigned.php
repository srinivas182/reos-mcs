<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\MentorAssignment;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** A mentor was assigned (A13.1a, broker screen 23): the agent and the mentor are told, with a first session suggested. */
final class MentorAssigned extends CentreNotification
{
    public function __construct(public readonly MentorAssignment $assignment, public readonly bool $forMentor) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::Mentoring;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $name = (string) ($this->forMentor ? $this->assignment->agent->name : $this->assignment->mentor->name);
        $title = __($this->forMentor ? 'mentoring.notify.newMentee' : 'mentoring.notify.assigned', ['name' => $name]);
        $body = __($this->forMentor ? 'mentoring.notify.newMenteeBody' : 'mentoring.notify.assignedBody', ['name' => $name]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url($this->forMentor ? '/bookings' : '/mentors')];
    }

    public function appPath(): string
    {
        return $this->forMentor ? '/bookings' : '/mentors';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('mentoring.notify.open'), $inbox['url'] ?? url('/'));
    }
}
