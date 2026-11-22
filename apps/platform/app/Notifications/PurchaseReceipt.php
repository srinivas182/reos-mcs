<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;

/** "You're in" (B5.1, screen 3): what was bought, what it cost with tax, and that the app is already unlocked. */
final class PurchaseReceipt extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $total = Number::currency(($this->order->total_cents ?? $this->order->chargeCents()) / 100, 'USD', 'en_US');
        $mail = (new MailMessage)
            ->subject(__('billing.receipt.subject', ['product' => $this->order->product->name]))
            ->line(__('billing.receipt.body', ['product' => $this->order->product->name, 'total' => $total]))
            ->line(__('billing.receipt.unlocked'));

        return $this->order->receipt_url !== null
            ? $mail->action(__('billing.receipt.action'), $this->order->receipt_url)
            : $mail->action(__('billing.receipt.billing'), url('/settings/billing'));
    }
}
