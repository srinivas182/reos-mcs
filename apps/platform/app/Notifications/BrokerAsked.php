<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\IntegrationInterest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "James Miller asked you to set up Lone Wolf Transactions" (A4.3a), to the
 * brokerage's owners. Sent a minute late and only if the ask still stands,
 * so the agent's Undo stops it.
 */
final class BrokerAsked extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly User $agent, public readonly string $integrationId, public readonly string $tool) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return IntegrationInterest::query()->where([
            'integration_id' => $this->integrationId,
            'user_id' => $this->agent->id,
            'kind' => IntegrationInterest::BROKER,
        ])->exists();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('connections.askBroker.mail.subject', ['person' => $this->agent->name ?? '', 'tool' => $this->tool]))
            ->line(__('connections.askBroker.mail.body', ['person' => $this->agent->name ?? '', 'tool' => $this->tool]))
            ->line(__('connections.askBroker.mail.next'));
    }
}
