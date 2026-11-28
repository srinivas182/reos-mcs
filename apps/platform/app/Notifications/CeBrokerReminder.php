<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;

/** The broker's reminder about CE or SAE hours still to do (A16.2), opening the CE wallet. */
final class CeBrokerReminder extends CentreNotification
{
    public function __construct(public readonly string $broker, public readonly string $brokerage, public readonly float $hoursLeft, public readonly ?string $dueOn, public readonly bool $sae) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::CeRenewal;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $hours = rtrim(rtrim(number_format($this->hoursLeft, 1, '.', ''), '0'), '.');
        $when = $this->dueOn === null ? '' : self::longDate($this->dueOn);
        $title = __('brokerage.ce.notify.title', ['broker' => $this->broker]);
        $body = __($this->sae ? 'brokerage.ce.notify.sae' : 'brokerage.ce.notify.body', ['hours' => $hours, 'date' => $when, 'brokerage' => $this->brokerage]);

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/ce')];
    }

    public function appPath(): string
    {
        return '/ce';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('ce.wallet.notify.open'), $inbox['url'] ?? url('/'));
    }

    private static function longDate(string $date): string
    {
        $parsed = CarbonImmutable::parse($date);
        $local = $parsed->locale(app()->getLocale());

        return ($local instanceof CarbonImmutable ? $local : $parsed)->isoFormat('LL');
    }
}
