<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** Someone signed in at an open house or wrote from the agent's page (B14.3a); opens the contact. */
final class NewLeadReceived extends CentreNotification
{
    /** @param 'open_house'|'page'|'team' $source */
    public function __construct(public readonly string $source, public readonly string $name, public readonly string $contact) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::NewLead;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('leads.notify.'.$this->source, ['name' => $this->name]);
        $body = __('leads.notify.body');

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/contacts?contact='.$this->contact)];
    }

    public function appPath(): string
    {
        return '/contacts/'.$this->contact;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('leads.notify.open'), $inbox['url'] ?? url('/'));
    }
}
