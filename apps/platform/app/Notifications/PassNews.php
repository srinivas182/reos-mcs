<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PassAnnouncement;
use App\Models\State;
use App\Notifications\Centre\CentreNotification;
use App\Notifications\Centre\NotificationKind;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Passing news (A10.3b, f05 and f06): someone in the study group or school
 * passed (`passed`), or a classmate sent congrats (`congrats`). People can
 * mute these in notification settings.
 */
final class PassNews extends CentreNotification
{
    public const PASSED = 'passed';

    public const CONGRATS = 'congrats';

    public function __construct(public readonly PassAnnouncement $pass, public readonly string $change, public readonly string $from = '', public readonly string $message = '') {}

    public function kind(): NotificationKind
    {
        return NotificationKind::PassNews;
    }

    /** @return array{title: string, body: string|null, url: string|null} */
    public function inbox(object $notifiable): array
    {
        $state = State::query()->where('code', $this->pass->state_code)->first()->name ?? $this->pass->state_code;
        $title = $this->change === self::CONGRATS
            ? __('licence.news.mail.congrats', ['name' => $this->from])
            : __($this->pass->city === null ? 'licence.news.mail.passed' : 'licence.news.mail.passedCity', ['name' => $this->pass->first_name, 'city' => (string) $this->pass->city, 'state' => $state]);
        $body = $this->change === self::CONGRATS ? $this->message : $this->pass->tip;

        return ['title' => is_string($title) ? $title : '', 'body' => $body, 'url' => url($this->appPath())];
    }

    public function appPath(): string
    {
        return $this->change === self::CONGRATS ? '/licence-journey' : '/licence-journey/passes';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inbox = $this->inbox($notifiable);

        return (new MailMessage)->subject($inbox['title'])->line($inbox['body'] ?? '')->action(__('licence.news.mail.open'), $inbox['url'] ?? url('/licence-journey'));
    }
}
