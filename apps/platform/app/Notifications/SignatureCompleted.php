<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/** Everyone signed (B9.3): the signed copy is on the deal. "Signed: Seller's Disclosure, 2207 Bellaire". */
final class SignatureCompleted extends CentreNotification
{
    public function __construct(public readonly string $deal, public readonly string $subject, public readonly string $property) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::SignatureCompleted;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $title = __('deals.signatures.mail.title', ['subject' => $this->subject, 'property' => $this->property]);
        $body = __('deals.signatures.mail.body');

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/deals?deal='.$this->deal)];
    }

    public function appPath(): string
    {
        return '/deals/'.$this->deal;
    }

    public function collapseKey(): string
    {
        return 'signed-'.$this->deal.'-'.md5($this->subject);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('deals.mail.open'), $inbox['url'] ?? url('/deals'));
    }
}
