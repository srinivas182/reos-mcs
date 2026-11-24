<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\BrokerQuestion;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** The broker answered the agent's question: Confirmed or Corrected (A12.1; agent screen 62). */
final class PolicyQuestionAnswered extends CentreNotification
{
    public function __construct(public readonly BrokerQuestion $question) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::PolicyAnswer;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('virtualBroker.notify.answeredTitle.'.$this->question->status);
        $body = __('virtualBroker.notify.answeredBody', ['broker' => (string) $this->question->answerer?->name]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/virtual-broker?question='.$this->question->uuid)];
    }

    public function appPath(): string
    {
        return '/ask/broker/'.$this->question->uuid;
    }

    /** The newest answer to the same question replaces the last one. */
    public function collapseKey(): string
    {
        return "policy-answer-{$this->question->uuid}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('virtualBroker.notify.open'), $inbox['url'] ?? url('/'));
    }
}
