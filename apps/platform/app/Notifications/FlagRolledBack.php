<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Features\RolloutStage;
use App\Models\FeatureFlag;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** Tells super admins a flag's rollout stepped back on its own (A6.2). */
final class FlagRolledBack extends CentreNotification
{
    public function __construct(
        public readonly FeatureFlag $flag,
        public readonly RolloutStage $from,
        public readonly float $rate,
    ) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::FlagRolledBack;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('features.notifications.rolledBack.subject', ['name' => $this->flag->name]);

        return ['title' => is_string($title) ? $title : '', 'body' => null, 'url' => route('features.index')];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $stage = static function (RolloutStage $stage): string {
            $label = __("features.stages.{$stage->value}");

            return is_string($label) ? $label : $stage->value;
        };

        return (new MailMessage)
            ->subject(__('features.notifications.rolledBack.subject', ['name' => $this->flag->name]))
            ->line(__('features.notifications.rolledBack.body', [
                'name' => $this->flag->name,
                'rate' => (string) round($this->rate, 1),
                'from' => $stage($this->from),
                'to' => $stage($this->flag->stage),
            ]))
            ->action(__('features.notifications.rolledBack.action'), route('features.index'))
            ->line(__('features.notifications.rolledBack.next'));
    }
}
