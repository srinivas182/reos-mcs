<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A renewal didn't go through (B5.1): access continues until the grace period ends; update the card to keep it. */
final class PaymentFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Subscription $subscription) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
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
