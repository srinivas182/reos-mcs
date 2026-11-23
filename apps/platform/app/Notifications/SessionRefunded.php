<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** The instructor didn't join within 10 minutes (A10.2a): the session is called off, and a paid one refunded in full. */
final class SessionRefunded extends CentreNotification
{
    public function __construct(public readonly string $booking, public readonly string $instructor, public readonly bool $paid) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::SessionBooked;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('sessions.booking.mail.noShow', ['name' => $this->instructor]);
        $body = __($this->paid ? 'sessions.booking.mail.refunded' : 'sessions.booking.mail.rebook');

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/sessions')];
    }

    public function appPath(): string
    {
        return '/sessions';
    }

    public function collapseKey(): string
    {
        return 'booking-refunded-'.$this->booking;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('sessions.booking.mail.open'), url('/sessions'));
    }
}
