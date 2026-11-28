<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ClientPortal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** "Maria Garcia shared your deal with you" (B14.2): the client's portal link, by email, in their language. */
final class ClientPortalLink extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ClientPortal $portal, public readonly string $link, public readonly string $agent) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lang = $this->portal->language;
        $words = ['agent' => $this->agent, 'name' => $this->portal->name];

        return (new MailMessage)
            ->subject(self::tr('portal.mail.subject', $words, $lang))
            ->greeting(self::tr('portal.mail.greeting', $words, $lang))
            ->line(self::tr('portal.mail.body', $words, $lang))
            ->action(self::tr('portal.mail.action', $words, $lang), $this->link)
            ->line(self::tr('portal.mail.private', $words, $lang));
    }

    /** @param array<string, string> $words */
    private static function tr(string $key, array $words, string $lang): string
    {
        $value = __($key, $words, $lang);

        return is_string($value) ? $value : $key;
    }
}
