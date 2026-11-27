<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** Another agent rated you after a closing (A13.3b); what they said shows once there are three ratings. */
final class AgentFeedbackReceived extends CentreNotification
{
    public function __construct(public readonly bool $shown) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::AgentFeedback;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('feedback.notify.title');
        $body = __($this->shown ? 'feedback.notify.shown' : 'feedback.notify.waiting');

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/feedback')];
    }

    public function appPath(): string
    {
        return '/feedback';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('feedback.notify.open'), $inbox['url'] ?? url('/'));
    }
}
