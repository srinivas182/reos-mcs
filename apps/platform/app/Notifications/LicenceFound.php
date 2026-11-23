<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LicenceJourney;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** The licence the candidate entered is now active on the regulator's record (A10.3c): switch to agent. */
final class LicenceFound extends CentreNotification
{
    public function __construct(public readonly LicenceJourney $journey) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::LicenceFound;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('licence.switch.mail.title');
        $body = __('licence.switch.mail.body', ['number' => (string) $this->journey->licence_number]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url($this->appPath())];
    }

    public function appPath(): string
    {
        return '/licence-journey/licence';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('licence.switch.mail.open'), $inbox['url'] ?? url('/'));
    }
}
