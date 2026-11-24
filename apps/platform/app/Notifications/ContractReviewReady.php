<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ContractReview;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The contract review is ready, or couldn't be done (A12.2; screen 64:
 * "Leave the screen → notification when done"). It names the file only,
 * so nothing from the contract shows on a lock screen.
 */
final class ContractReviewReady extends CentreNotification
{
    public function __construct(public readonly ContractReview $review) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::ContractReview;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $done = $this->review->status === 'done';
        $high = count(array_filter($this->review->findings ?? [], static fn (array $f): bool => $f['severity'] === 'high'));
        $title = __($done ? 'contracts.notify.readyTitle' : 'contracts.notify.failedTitle');
        $body = $done ? trans_choice('contracts.notify.readyBody', $high, ['count' => (string) $high, 'file' => $this->review->file_name]) : __('contracts.notify.failedBody', ['file' => $this->review->file_name]);

        return ['title' => is_string($title) ? $title : '', 'body' => $body, 'url' => url('/contracts/'.$this->review->uuid)];
    }

    public function appPath(): string
    {
        return '/contracts/'.$this->review->uuid;
    }

    public function collapseKey(): string
    {
        return "contract-review-{$this->review->uuid}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('contracts.notify.open'), $inbox['url'] ?? url('/'));
    }
}
