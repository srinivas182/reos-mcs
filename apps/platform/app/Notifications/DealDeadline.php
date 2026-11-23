<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** A deal's key date is 72 or 24 hours away (B9.1, Deal alerts). */
final class DealDeadline extends CentreNotification
{
    public function __construct(public readonly string $deal, public readonly string $property, public readonly string $date, public readonly string $dueAt, public readonly int $hours) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::DealDeadline;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __($this->hours === 24 ? 'deals.mail.tomorrow' : 'deals.mail.soon', ['date' => $this->date]);
        $body = __('deals.mail.body', ['property' => $this->property]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/deals?deal='.$this->deal)];
    }

    /** The phone's deal screen; the web opens the deal on the board. */
    public function appPath(): string
    {
        return '/deals/'.$this->deal;
    }

    public function collapseKey(): string
    {
        return 'deal-'.$this->deal.'-'.$this->date;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('deals.mail.open'), $inbox['url'] ?? url('/deals'));
    }
}
