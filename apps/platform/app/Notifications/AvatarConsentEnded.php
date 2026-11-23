<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\AvatarConsent;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * "Dr. Rachel Hughes withdrew avatar consent" (A7.1b), to the school's admins:
 * lessons using the avatar must be unpublished or re-rendered by the date.
 */
final class AvatarConsentEnded extends CentreNotification
{
    public function __construct(public readonly AvatarConsent $consent) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::AvatarConsentEnded;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        return ['title' => $this->title(), 'body' => $this->body(), 'url' => url('/school/instructors')];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->body())
            ->action(self::text('avatarConsent.mail.action'), url('/school/instructors'));
    }

    private function title(): string
    {
        return self::text($this->consent->status === AvatarConsent::WITHDRAWN ? 'avatarConsent.mail.withdrawnSubject' : 'avatarConsent.mail.endedSubject', ['name' => $this->consent->user->name ?? '']);
    }

    private function body(): string
    {
        return self::text('avatarConsent.mail.body', ['name' => $this->consent->user->name ?? '', 'date' => $this->consent->lessons_due_by?->toFormattedDateString() ?? '']);
    }

    /** @param  array<string, string>  $values */
    private static function text(string $key, array $values = []): string
    {
        $text = __($key, $values);

        return is_string($text) ? $text : $key;
    }
}
