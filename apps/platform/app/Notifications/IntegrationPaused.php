<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Integrations\IntegrationDefinition;
use App\Models\IntegrationCheck;
use App\Services\Integrations\HealthMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells super admins that health checks paused an integration. */
final class IntegrationPaused extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly IntegrationDefinition $definition,
        public readonly IntegrationCheck $check,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
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
