<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\State;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** "Texas CE rules change on 1 September 2027" (A5.2, rule change alert), to every licence holder in the state. */
final class StateRulesChanged extends CentreNotification
{
    /** @param list<string> $keys */
    public function __construct(public readonly State $state, public readonly array $keys, public readonly string $from) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::StateRulesChanged;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('stateRules.changedMail.subject', ['state' => $this->state->name]);

        return ['title' => is_string($title) ? $title : '', 'body' => null, 'url' => url('/settings/profile')];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('stateRules.changedMail.subject', ['state' => $this->state->name]))
            ->line(__('stateRules.changedMail.body', ['state' => $this->state->name, 'date' => $this->from]));

        foreach ($this->keys as $key) {
            $mail->line('• '.__('stateRules.keys.'.$key));
        }

        return $mail->action(__('stateRules.changedMail.action'), url('/settings/profile'))->line(__('stateRules.changedMail.next'));
    }
}
