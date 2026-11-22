<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SupportAccessGrant;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use App\Services\Tenancy\SupportAccess;
use Illuminate\Notifications\Messages\MailMessage;

/** "REOS support asks to sign in to Lone Star Realty" (A4.1c), to each of the tenant's owners. */
final class SupportAccessRequested extends CentreNotification
{
    public function __construct(public readonly SupportAccessGrant $grant) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::SupportAccess;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('support.mail.subject', ['tenant' => $this->grant->organisation->name]);

        return ['title' => is_string($title) ? $title : '', 'body' => null, 'url' => route('support-access.show', ['grant' => $this->grant->id])];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = $this->grant->organisation->name;

        return (new MailMessage)
            ->subject(__('support.mail.subject', ['tenant' => $tenant]))
            ->line(__('support.mail.body', ['person' => $this->grant->requester->name, 'tenant' => $tenant]))
            ->line(__('support.mail.reason', ['reason' => $this->grant->reason]))
            ->line(__('support.mail.terms', ['minutes' => SupportAccess::MINUTES]))
            ->action(__('support.mail.action'), route('support-access.show', ['grant' => $this->grant->id]))
            ->line(__('support.mail.ignore', ['hours' => SupportAccess::ANSWER_HOURS]));
    }
}
