<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\VideoSession;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * A CE class report (A10.2c): ready for the instructor when the class ends
 * (`ready`), or confirmed and sent to the school's admins (`sent`).
 */
final class ClassReportReady extends CentreNotification
{
    public const READY = 'ready';

    public const SENT = 'sent';

    public function __construct(public readonly VideoSession $session, public readonly string $change = self::READY, public readonly string $by = '') {}

    public function kind(): NotificationKind
    {
        return NotificationKind::ClassReport;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __("classes.mail.report.{$this->change}.title", ['title' => $this->session->title]);
        $body = __("classes.mail.report.{$this->change}.body", ['name' => $this->by]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url($this->appPath())];
    }

    public function appPath(): string
    {
        return '/classes/'.$this->session->uuid.'/report';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('classes.report.open'), $inbox['url'] ?? url('/classes'));
    }
}
