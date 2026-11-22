<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The daily or weekly digest (B6.2): everything gathered since the last one, in one email. */
final class Digest extends Notification
{
    use Queueable;

    /** @param  list<array{title: string, body: string|null, url: string|null}>  $items */
    public function __construct(public readonly string $frequency, public readonly array $items) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__("notifications.digest.subject.{$this->frequency}_".(count($this->items) === 1 ? 'one' : 'other'), ['count' => (string) count($this->items)]))
            ->line(__("notifications.digest.intro.{$this->frequency}"));

        foreach ($this->items as $item) {
            $mail->line('• '.$item['title'].($item['body'] !== null ? ': '.$item['body'] : '').($item['url'] !== null ? ' ('.$item['url'].')' : ''));
        }

        return $mail->action(__('notifications.digest.settings'), url('/settings/notifications'));
    }
}
