<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Integrations\Catalogue;
use App\Models\Connection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Reconnect Google Calendar": the tool stopped accepting REOS's sign-in. */
final class ConnectionNeedsReconnect extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Connection $toolConnection) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = app(Catalogue::class)->find($this->toolConnection->integration_id)->name ?? $this->toolConnection->integration_id;
        $account = $this->toolConnection->account_label ?? '';

        return (new MailMessage)
            ->subject(__('connections.reconnect.subject', ['tool' => $name]))
            ->line(__('connections.reconnect.body', ['tool' => $name, 'account' => $account]))
            ->action(__('connections.reconnect.action', ['tool' => $name]), route('connections.connect', ['tool' => $this->toolConnection->integration_id]))
            ->line(__('connections.reconnect.safe'));
    }
}
