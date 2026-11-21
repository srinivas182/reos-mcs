<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Linda Park asks for more seats for Lone Star Realty" (A4.3a), to the tenant's owners or REOS. */
final class SeatsRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Organisation $tenant,
        public readonly User $requester,
        public readonly ?int $limit,
        public readonly ?string $note,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
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
