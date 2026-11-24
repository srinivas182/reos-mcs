<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SocialPost;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** The broker approved the agent's post, or sent it back with a note (B12.1). */
final class PostApprovalDecided extends CentreNotification
{
    public function __construct(public readonly SocialPost $post, public readonly bool $approved) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::PostDecision;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __($this->approved ? 'studio.notify.approvedTitle' : 'studio.notify.returnedTitle');
        $body = $this->approved ? null : $this->post->approval_note;

        return ['title' => is_string($title) ? $title : '', 'body' => $body, 'url' => url('/studio')];
    }

    public function appPath(): string
    {
        return '/social/'.$this->post->uuid;
    }

    public function collapseKey(): string
    {
        return "post-decision-{$this->post->uuid}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('studio.notify.open'), $inbox['url'] ?? url('/'));
    }
}
