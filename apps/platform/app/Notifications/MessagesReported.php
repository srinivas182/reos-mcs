<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Authorization\Role;
use App\Models\MessageReport;
use App\Models\User;
use App\Services\Authorization\Memberships;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the tenant's admins and REOS that someone reported messages (B6.1b). */
final class MessagesReported extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly MessageReport $report) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $reason = __("messages.report.reasons.{$this->report->reason}");

        $mail = (new MailMessage)
            ->subject(__('messages.report.mail.subject'))
            ->line(__('messages.report.mail.body', [
                'reporter' => $this->report->reporter->name ?? __('messages.formerMember'),
                'reported' => $this->report->reported->name ?? __('messages.report.mail.someone'),
                'reason' => is_string($reason) ? $reason : $this->report->reason,
                'count' => (string) count($this->report->messages),
            ]));

        if ($this->report->details !== null) {
            $mail->line(__('messages.report.mail.details', ['details' => $this->report->details]));
        }

        // The reported messages as they were when reported.
        foreach ($this->report->messages as $message) {
            $mail->line(($message['sender'] ?? __('messages.formerMember')).': '.($message['body'] ?? __('messages.deleted')));
        }

        // REOS reviews reports on Compliance and audit (screen 71).
        $isReos = $notifiable instanceof User && array_filter(app(Memberships::class)->of($notifiable), static fn ($m): bool => $m->role === Role::SuperAdmin) !== [];

        if ($isReos) {
            $mail->action(__('messages.report.mail.action'), route('compliance.index').'#message-reports');
        }

        return $mail->line(__('messages.report.mail.privacy'));
    }
}
