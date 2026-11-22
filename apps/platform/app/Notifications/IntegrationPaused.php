<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Integrations\IntegrationDefinition;
use App\Models\IntegrationCheck;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use App\Services\Integrations\HealthMonitor;
use Illuminate\Notifications\Messages\MailMessage;

/** Tells super admins that health checks paused an integration. */
final class IntegrationPaused extends CentreNotification
{
    public function __construct(
        public readonly IntegrationDefinition $definition,
        public readonly IntegrationCheck $check,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::IntegrationPaused;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('integrations.notifications.paused.subject', ['name' => $this->definition->name]);

        return ['title' => is_string($title) ? $title : '', 'body' => null, 'url' => route('integrations.show', $this->definition->id)];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->definition->name;
        $parameters = array_map(strval(...), $this->check->parameters);

        if (isset($parameters['field'])) {
            $label = __("integrations.fields.{$parameters['field']}");
            $parameters['field'] = is_string($label) ? $label : $parameters['field'];
        }

        $reason = __($this->check->message, $parameters);

        return (new MailMessage)
            ->subject(__('integrations.notifications.paused.subject', ['name' => $name]))
            ->line(__('integrations.notifications.paused.body', ['name' => $name, 'count' => (string) HealthMonitor::PAUSE_AFTER]))
            ->line(__('integrations.notifications.paused.reason', ['reason' => is_string($reason) ? $reason : '']))
            ->action(__('integrations.notifications.paused.action'), route('integrations.show', $this->definition->id))
            ->line(__('integrations.notifications.paused.resume'));
    }
}
