<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\IntegrationInterest;
use App\Models\User;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * "James Miller asked you to set up Lone Wolf Transactions" (A4.3a), to the
 * brokerage's owners. Sent a minute late and only if the ask still stands,
 * so the agent's Undo stops it.
 */
final class BrokerAsked extends CentreNotification
{
    public function __construct(public readonly User $agent, public readonly string $integrationId, public readonly string $tool) {}

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return IntegrationInterest::query()->where([
            'integration_id' => $this->integrationId,
            'user_id' => $this->agent->id,
            'kind' => IntegrationInterest::BROKER,
        ])->exists();
    }

    public function kind(): NotificationKind
    {
        return NotificationKind::BrokerAsked;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('connections.askBroker.mail.subject', ['person' => $this->agent->name ?? '', 'tool' => $this->tool]);
        $body = __('connections.askBroker.mail.body', ['person' => $this->agent->name ?? '', 'tool' => $this->tool]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => null];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('connections.askBroker.mail.subject', ['person' => $this->agent->name ?? '', 'tool' => $this->tool]))
            ->line(__('connections.askBroker.mail.body', ['person' => $this->agent->name ?? '', 'tool' => $this->tool]))
            ->line(__('connections.askBroker.mail.next'));
    }
}
