<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Subscription;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** A renewal didn't go through (B5.1): access continues until the grace period ends; update the card to keep it. */
final class PaymentFailed extends CentreNotification
{
    public function __construct(public readonly Subscription $subscription) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::PaymentFailed;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('billing.failedMail.subject', ['product' => $this->subscription->product->name]);

        return ['title' => is_string($title) ? $title : '', 'body' => null, 'url' => url('/settings/billing')];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $grace = $this->subscription->grace_ends_at;
        $date = $grace === null ? '' : $grace->isoFormat('LL');

        return (new MailMessage)
            ->subject(__('billing.failedMail.subject', ['product' => $this->subscription->product->name]))
            ->line(__('billing.failedMail.body', ['product' => $this->subscription->product->name, 'date' => $date]))
            ->action(__('billing.failedMail.action'), url('/settings/billing'));
    }
}
