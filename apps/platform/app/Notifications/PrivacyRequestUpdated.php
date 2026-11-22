<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PrivacyRequest;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use App\Services\Privacy\PrivacyRequests;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The person hears about their privacy request (A4.3b): received with the
 * date REOS answers by, extended (with why), done (with the download link
 * for "see my data") or declined (with why).
 */
final class PrivacyRequestUpdated extends CentreNotification
{
    public function __construct(public readonly PrivacyRequest $request, public readonly ?string $downloadLink = null) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::PrivacyRequest;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('privacy.inbox.updated');

        return ['title' => is_string($title) ? $title : '', 'body' => null, 'url' => url('/settings/profile')];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $kind = __('privacy.kinds.'.$this->request->kind);
        $state = match (true) {
            $this->request->status === PrivacyRequest::DONE => 'done',
            $this->request->status === PrivacyRequest::DECLINED => 'declined',
            $this->request->extended && $this->request->waiting() => 'extended',
            default => 'received',
        };

        $mail = (new MailMessage)
            ->subject(__("privacy.mail.{$state}.subject", ['kind' => $kind]))
            ->line(__("privacy.mail.{$state}.body", ['kind' => $kind, 'date' => $this->request->due_at->toFormattedDateString()]));

        if ($this->request->response !== null && in_array($state, ['done', 'declined', 'extended'], true)) {
            $mail->line(__('privacy.mail.note', ['note' => $this->request->response]));
        }

        if ($this->downloadLink !== null) {
            $mail->action(__('privacy.mail.download'), $this->downloadLink)
                ->line(__('privacy.mail.linkDays', ['days' => PrivacyRequests::LINK_DAYS]));
        }

        return $mail;
    }
}
