<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\BrokerOffer;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Broker offers (A10.3b, screen 17): a new offer for the candidate
 * (`offer`), or the candidate's answer for the brokerage (`accepted`, with
 * their contact details, or `declined`).
 */
final class BrokerOfferNotice extends CentreNotification
{
    public const OFFER = 'offer';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    public function __construct(public readonly BrokerOffer $offer, public readonly string $change) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::BrokerOffer;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $candidate = $this->offer->candidate;
        $title = match ($this->change) {
            self::OFFER => __('licence.offers.mail.offer', ['brokerage' => $this->offer->organisation->name]),
            self::ACCEPTED => __('licence.offers.mail.accepted', ['name' => (string) $candidate->name]),
            default => __('licence.offers.mail.declined', ['name' => self::firstName((string) $candidate->name)]),
        };
        $body = match ($this->change) {
            self::OFFER => __('licence.offers.mail.offerBody', ['split' => $this->offer->split_percent, 'city' => $this->offer->office_city]),
            // Contact details go to the brokerage only once the candidate accepts.
            self::ACCEPTED => __('licence.offers.mail.acceptedBody', ['email' => (string) $candidate->email, 'phone' => (string) ($candidate->phone ?? '—')]),
            default => null,
        };

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url($this->appPath())];
    }

    public function appPath(): string
    {
        return $this->change === self::OFFER ? '/licence-journey/offers' : '/recruiting';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('licence.offers.mail.open'), $inbox['url'] ?? url('/'));
    }

    public static function firstName(string $name): string
    {
        return explode(' ', trim($name))[0] ?: $name;
    }
}
