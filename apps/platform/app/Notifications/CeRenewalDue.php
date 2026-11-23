<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Licence;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * CE before a licence renewal (A11.2): hours still missing 60, 30 or 7 days
 * before, or "Renewal ready" when they're done.
 */
final class CeRenewalDue extends CentreNotification
{
    public function __construct(public readonly Licence $licence, public readonly string $kind, public readonly float $hoursLeft, public readonly string $dueOn) {}

    public function kind(): NotificationKind
    {
        return NotificationKind::CeRenewal;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $date = CarbonImmutable::parse($this->dueOn);
        $local = $date->locale(app()->getLocale());
        $when = ($local instanceof CarbonImmutable ? $local : $date)->isoFormat('LL');
        $hours = rtrim(rtrim(number_format($this->hoursLeft, 1, '.', ''), '0'), '.');

        if ($this->kind === 'ready') {
            $title = __('ce.wallet.notify.readyTitle');
            $body = __('ce.wallet.notify.readyBody', ['date' => $when]);
        } else {
            $title = __('ce.wallet.notify.dueTitle', ['days' => (string) $date->diffInDays(CarbonImmutable::today()->startOfDay(), true)]);
            $body = __('ce.wallet.notify.dueBody', ['hours' => $hours, 'date' => $when]);
        }

        return ['title' => is_string($title) ? $title : '', 'body' => is_string($body) ? $body : null, 'url' => url('/ce')];
    }

    public function appPath(): string
    {
        return '/ce';
    }

    /** The newest reminder replaces the last one for the same licence. */
    public function collapseKey(): string
    {
        return "ce-renewal-{$this->licence->id}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('ce.wallet.notify.open'), $inbox['url'] ?? url('/'));
    }
}
