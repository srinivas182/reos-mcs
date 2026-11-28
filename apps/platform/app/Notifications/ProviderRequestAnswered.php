<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ProviderRequest;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use App\ProviderNetwork\AgentsInArea;
use Illuminate\Notifications\Messages\MailMessage;

/** An agent answered a provider's request to connect (B16.2): accepted opens the chat; declined says no more. */
final class ProviderRequestAnswered extends CentreNotification
{
    public function __construct(public readonly ProviderRequest $request, public readonly bool $accepted) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::ProviderRequest;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $name = $this->accepted ? (string) $this->request->agent->name : AgentsInArea::shortName((string) $this->request->agent->name);
        $title = __($this->accepted ? 'providers.network.notify.accepted' : 'providers.network.notify.declined', ['name' => $name]);
        $body = $this->accepted ? __('providers.network.notify.acceptedBody') : null;

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url($this->accepted ? '/messages' : '/provider/agents')];
    }

    public function appPath(): string
    {
        return $this->accepted ? '/messages' : '/provider/agents';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action($inbox['title'], $inbox['url'] ?? url('/'));
    }
}
