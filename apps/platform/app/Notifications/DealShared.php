<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DealShare;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** An agent shared a deal with the coordinator (B17.1): it's on My files until closing. */
final class DealShared extends CentreNotification
{
    public function __construct(public readonly DealShare $share) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::DealDeadline;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('coordinators.notifyTitle', ['agent' => (string) $this->share->agent->name]);
        $body = __('coordinators.notifyBody', ['property' => $this->share->deal->property]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/provider/files')];
    }

    public function appPath(): string
    {
        return '/provider/files';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action($inbox['title'], $inbox['url'] ?? url('/'));
    }
}
