<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ProviderProfile;
use App\Models\ProviderRequest;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * A verified provider asks to connect (B16.1). It names the business and
 * the category; the agent accepts or declines in Provider requests (B16.2).
 */
final class ProviderRequestReceived extends CentreNotification
{
    public function __construct(public readonly ProviderRequest $request, public readonly ProviderProfile $profile) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::ProviderRequest;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $name = $this->profile->business_name ?? (string) $this->profile->user->name;
        $title = __('providers.agents.notify.title', ['provider' => $name]);
        $body = __('providers.agents.notify.body', ['category' => $this->profile->category->name ?? '', 'days' => 14]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/providers/requests')];
    }

    public function appPath(): string
    {
        return '/providers/requests';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action($inbox['title'], $inbox['url'] ?? url('/'));
    }
}
