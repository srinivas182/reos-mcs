<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invitation;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * "Linda Park invited you to Lone Star Realty" (A4.3a), by email, or as a
 * text through {@see text()}. In the language chosen when inviting, with
 * the link, the code and the expiry date. The day-7 reminder says so.
 */
final class InvitationSent extends CentreNotification
{
    public function __construct(public readonly Invitation $invitation, public readonly string $link, public readonly bool $reminder = false) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::Invitation;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __($this->reminder ? 'invites.mail.reminderSubject' : 'invites.mail.subject', $this->words(), $this->invitation->locale);

        return ['title' => is_string($title) ? $title : '', 'body' => null, 'url' => $this->link];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $words = $this->words();

        return (new MailMessage)
            ->subject(__($this->reminder ? 'invites.mail.reminderSubject' : 'invites.mail.subject', $words, $this->invitation->locale))
            ->greeting(__('invites.mail.greeting', ['name' => $this->invitation->name ?? ''], $this->invitation->locale))
            ->line(__($words['team'] === '' ? 'invites.mail.body' : 'invites.mail.bodyTeam', $words, $this->invitation->locale))
            ->action(__('invites.mail.action', $words, $this->invitation->locale), $this->link)
            ->line(__('invites.mail.code', $words, $this->invitation->locale))
            ->line(__('invites.mail.expires', $words, $this->invitation->locale))
            ->line(__('invites.mail.ignore', [], $this->invitation->locale));
    }

    /** The text message: short, with the link and code. */
    public function text(): string
    {
        return __($this->reminder ? 'invites.text.reminder' : 'invites.text.body', [...$this->words(), 'link' => $this->link], $this->invitation->locale);
    }

    /** @return array<string, string> */
    private function words(): array
    {
        $locale = $this->invitation->locale;

        return [
            'inviter' => $this->invitation->inviter->name ?? $this->invitation->organisation->name,
            'tenant' => $this->invitation->organisation->name,
            'role' => __('roles.names.'.$this->invitation->role->value, [], $locale),
            'team' => $this->invitation->team !== null ? $this->invitation->team->name : '',
            'code' => $this->invitation->secret['code'],
            'date' => (new \IntlDateFormatter($locale, \IntlDateFormatter::LONG, \IntlDateFormatter::NONE))->format($this->invitation->expires_at) ?: $this->invitation->expires_at->toDateString(),
        ];
    }
}
