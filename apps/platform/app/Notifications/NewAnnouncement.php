<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Announcement;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/** An announcement from the person's brokerage, school or association (B6.1b), in the inbox (and by email if they choose). */
final class NewAnnouncement extends CentreNotification
{
    /** @param bool $reminder A reminder to someone who hasn't read it yet (A16.3). */
    public function __construct(public readonly Announcement $announcement, public readonly bool $reminder = false) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::Announcement;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        return ['title' => $this->reminder ? __('announcements.receipts.reminderTitle', ['title' => $this->announcement->title]) : $this->announcement->title, 'body' => Str::limit($this->announcement->body, 160), 'url' => url('/messages')];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->reminder ? __('announcements.receipts.reminderTitle', ['title' => $this->announcement->title]) : $this->announcement->title)
            ->line(__('announcements.mail.from', ['from' => $this->announcement->organisation->name]))
            ->line($this->announcement->body)
            ->action(__('announcements.mail.open'), url('/messages'));
    }
}
