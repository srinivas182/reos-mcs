<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * A sponsorship change (A16.2): the agent is released on a date, or open
 * deals were handed over to the person told.
 */
final class SponsorshipChanged extends CentreNotification
{
    /** @param  'released'|'handover'  $what */
    public function __construct(public readonly string $what, public readonly string $brokerage, public readonly string $agent, public readonly string $effectiveOn, public readonly int $deals) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::Sponsorship;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $date = self::longDate($this->effectiveOn);
        $title = __("brokerage.sponsorship.notify.{$this->what}Title", ['brokerage' => $this->brokerage, 'agent' => $this->agent]);
        $body = __("brokerage.sponsorship.notify.{$this->what}Body", ['date' => $date, 'count' => (string) $this->deals, 'agent' => $this->agent]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url($this->what === 'handover' ? '/deals' : '/notifications')];
    }

    public function appPath(): string
    {
        return $this->what === 'handover' ? '/deals' : '/notifications';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '');
    }

    private static function longDate(string $date): string
    {
        $parsed = CarbonImmutable::parse($date);
        $local = $parsed->locale(app()->getLocale());

        return ($local instanceof CarbonImmutable ? $local : $parsed)->isoFormat('LL');
    }
}
