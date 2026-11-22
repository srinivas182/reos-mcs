<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Models\VideoSession;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationCentre;
use App\Notifications\Centre\NotificationKind;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * A session was booked with you, or cancelled (B6.3): when, how long and
 * with whom, in your own time zone.
 */
final class SessionBooked extends CentreNotification
{
    public const BOOKED = 'booked';

    public const CANCELLED = 'cancelled';

    public function __construct(public readonly VideoSession $session, public readonly string $change) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::SessionBooked;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('sessions.mail.'.$this->change.'.title', ['host' => $this->session->host->name ?? '', 'title' => $this->session->title]);
        $body = __('sessions.mail.when', ['when' => self::when($this->session, $notifiable), 'minutes' => $this->session->starts_at->diffInMinutes($this->session->ends_at)]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/sessions/'.$this->session->uuid)];
    }

    public function appPath(): string
    {
        return '/sessions/'.$this->session->uuid;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);
        $mail = (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '');

        return $this->change === self::BOOKED
            ? $mail->line(__('sessions.mail.joinWhen'))->action(__('sessions.mail.open'), url('/sessions/'.$this->session->uuid))
            : $mail;
    }

    /** "Tue, Nov 24, 2026 3:00 PM CST" in the person's notification time zone. */
    public static function when(VideoSession $session, object $notifiable): string
    {
        $zone = $notifiable instanceof User ? app(NotificationCentre::class)->settings($notifiable)->timezone : 'America/Chicago';

        $at = CarbonImmutable::instance($session->starts_at)->setTimezone($zone)->locale(app()->getLocale());

        return $at instanceof CarbonImmutable ? $at->isoFormat('llll z') : '';
    }
}
