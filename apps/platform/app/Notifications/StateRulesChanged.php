<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\State;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Texas CE rules change on 1 September 2027" (A5.2, rule change alert), to every licence holder in the state. */
final class StateRulesChanged extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param list<string> $keys */
    public function __construct(public readonly State $state, public readonly array $keys, public readonly string $from) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
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
