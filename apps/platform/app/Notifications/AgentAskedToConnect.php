<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ProviderRequest;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** An agent connected with the provider from Providers near you (B16.3): the chat is open. */
final class AgentAskedToConnect extends CentreNotification
{
    public function __construct(public readonly ProviderRequest $request) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::ProviderRequest;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('providers.directory.connect.notifyTitle', ['name' => (string) $this->request->agent->name]);
        $body = __('providers.directory.connect.notifyBody');

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/messages')];
    }

    public function appPath(): string
    {
        return '/messages';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action($inbox['title'], $inbox['url'] ?? url('/'));
    }
}
