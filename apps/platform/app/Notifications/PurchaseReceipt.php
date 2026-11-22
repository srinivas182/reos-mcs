<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Number;

/** "You're in" (B5.1, screen 3): what was bought, what it cost with tax, and that the app is already unlocked. */
final class PurchaseReceipt extends CentreNotification
{
    public function __construct(public readonly Order $order) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::PurchaseReceipt;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('billing.receipt.subject', ['product' => $this->order->product->name]);

        return ['title' => is_string($title) ? $title : '', 'body' => null, 'url' => url('/settings/billing')];
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
