<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SocialPost;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** An agent's post waits for the broker's approval (B12.1; broker web screen 35). */
final class PostApprovalRequested extends CentreNotification
{
    public function __construct(public readonly SocialPost $post) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::PostApproval;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('studio.notify.requestedTitle', ['agent' => (string) $this->post->user->name]);
        $body = __('studio.notify.requestedBody');

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/organisation/advertising')];
    }

    public function appPath(): string
    {
        return '/notifications';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('studio.notify.review'), $inbox['url'] ?? url('/'));
    }
}
