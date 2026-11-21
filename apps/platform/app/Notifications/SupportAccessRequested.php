<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SupportAccessGrant;
use App\Services\Tenancy\SupportAccess;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "REOS support asks to sign in to Lone Star Realty" (A4.1c), to each of the tenant's owners. */
final class SupportAccessRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly SupportAccessGrant $grant) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
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
