<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * An approval or escalated question has waited past the brokerage's limit
 * in working hours (A15.3): the designated broker and office admins with
 * the duty are told, once. What the item says stays in REOS.
 */
final class ApprovalOverdue extends CentreNotification
{
    /** @param  'post'|'page'|'question'  $itemKind */
    public function __construct(public readonly string $itemKind, public readonly string $agent, public readonly int $hours) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::ApprovalOverdue;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('brokerage.approvals.notify.title.'.$this->itemKind, ['agent' => $this->agent]);
        $body = __('brokerage.approvals.notify.body', ['hours' => $this->hours]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/organisation/approvals')];
    }

    public function appPath(): string
    {
        return '/brokerage/approvals';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('brokerage.approvals.notify.open'), $inbox['url'] ?? url('/'));
    }
}
