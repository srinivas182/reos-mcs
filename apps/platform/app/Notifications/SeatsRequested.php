<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Organisation;
use App\Models\User;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** "Linda Park asks for more seats for Lone Star Realty" (A4.3a), to the tenant's owners or REOS. */
final class SeatsRequested extends CentreNotification
{
    public function __construct(
        public readonly Organisation $tenant,
        public readonly User $requester,
        public readonly ?int $limit,
        public readonly ?string $note,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::SeatsRequested;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('invites.seatsMail.subject', ['tenant' => $this->tenant->name]);

        return ['title' => is_string($title) ? $title : '', 'body' => null, 'url' => null];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('invites.seatsMail.subject', ['tenant' => $this->tenant->name]))
            ->line(__('invites.seatsMail.body', ['person' => $this->requester->name ?? '', 'tenant' => $this->tenant->name, 'limit' => $this->limit ?? '—']));

        if ($this->note !== null && trim($this->note) !== '') {
            $mail->line(__('invites.seatsMail.note', ['note' => $this->note]));
        }

        return $mail->line(__('invites.seatsMail.next'));
    }
}
