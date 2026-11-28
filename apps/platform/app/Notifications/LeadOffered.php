<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\RoutedLead;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** A team lead to claim (A17.3): the first in the rotation to claim in time gets it. Arrives at once. */
final class LeadOffered extends CentreNotification
{
    public function __construct(public readonly RoutedLead $lead, public readonly string $team, public readonly int $minutes) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::NewLead;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('teams.routing.offerTitle', ['team' => $this->team]);
        $body = __('teams.routing.offerBody', ['minutes' => $this->minutes]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/team/leads')];
    }

    public function appPath(): string
    {
        return '/team/leads';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action($inbox['title'], $inbox['url'] ?? url('/'));
    }
}
