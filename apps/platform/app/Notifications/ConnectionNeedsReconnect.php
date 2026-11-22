<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Integrations\Catalogue;
use App\Models\Connection;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** "Reconnect Google Calendar": the tool stopped accepting REOS's sign-in. */
final class ConnectionNeedsReconnect extends CentreNotification
{
    public function __construct(public readonly Connection $toolConnection) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::ConnectionNeedsReconnect;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('connections.reconnect.subject', ['tool' => $this->toolConnection->integration_id]);

        return ['title' => is_string($title) ? $title : '', 'body' => null, 'url' => route('connections.connect', ['tool' => $this->toolConnection->integration_id])];
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
