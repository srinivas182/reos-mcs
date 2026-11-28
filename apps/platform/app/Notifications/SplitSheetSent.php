<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SplitSheet;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** A split sheet to sign (A17.3). */
final class SplitSheetSent extends CentreNotification
{
    public function __construct(public readonly SplitSheet $sheet, public readonly string $team) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::SplitSheet;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('teams.splits.notifyTitle', ['property' => $this->sheet->property]);
        $body = __('teams.splits.notifyBody', ['team' => $this->team]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/team/leads')];
    }

    public function appPath(): string
    {
        return '/team/leads';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action($inbox['title'], $inbox['url'] ?? url('/'));
    }
}
