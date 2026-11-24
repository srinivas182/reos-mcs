<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\BrokerQuestion;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * An agent's question is waiting for the broker (A12.1; broker journey
 * screen 11): why it came and by when the brokerage aims to reply. The
 * question itself stays in REOS, so nothing private shows on a lock screen.
 */
final class PolicyQuestionEscalated extends CentreNotification
{
    public function __construct(public readonly BrokerQuestion $question) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::PolicyQuestion;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('virtualBroker.notify.escalatedTitle', ['agent' => (string) $this->question->user->name]);
        $body = __('virtualBroker.notify.escalatedBody.'.($this->question->reason ?? 'asked'));

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/organisation/virtual-broker?question='.$this->question->uuid)];
    }

    public function appPath(): string
    {
        return '/notifications';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('virtualBroker.notify.answer'), $inbox['url'] ?? url('/'));
    }
}
