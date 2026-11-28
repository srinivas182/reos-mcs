<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Leads\FarmUpdates;
use App\Models\FarmArea;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;

/**
 * A market update to one contact (B14.3c), in the agent's language: the
 * update as the agent approved it, the MLS source, the agent and broker,
 * the sender's postal address and a one-click unsubscribe (CAN-SPAM,
 * RFC 8058). Replies go to the agent. The unsubscribe link carries a hash
 * of the address, never the address itself.
 */
final class FarmUpdateMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly User $agent,
        public readonly FarmArea $area,
        public readonly string $subject,
        public readonly string $body,
        public readonly string $attribution,
        public readonly ?string $broker,
        public readonly string $email,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('farm.unsubscribe', ['agent' => $this->agent->id, 'e' => FarmUpdates::hash($this->email)]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lang = $this->agent->locale->value;
        $unsubscribe = $this->unsubscribeUrl();
        $mail = (new MailMessage)->subject($this->subject);

        foreach (preg_split('/\n{2,}/', $this->body) ?: [] as $paragraph) {
            if (trim($paragraph) !== '') {
                $mail->line(trim($paragraph));
            }
        }

        $mail->line($this->attribution)
            ->line(self::tr('leads.farm.mail.sender', ['agent' => (string) $this->agent->name, 'broker' => $this->broker ?? '', 'address' => $this->area->mailing_address], $lang))
            ->line(self::tr('leads.farm.mail.why', ['agent' => (string) $this->agent->name], $lang))
            ->action(self::tr('leads.farm.mail.unsubscribe', [], $lang), $unsubscribe)
            ->withSymfonyMessage(static function (Email $message) use ($unsubscribe): void {
                $message->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$unsubscribe.'>');
                $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            });

        if ($this->agent->email !== null) {
            $mail->replyTo($this->agent->email, (string) $this->agent->name);
        }

        return $mail;
    }

    /** @param array<string, string> $words */
    private static function tr(string $key, array $words, string $lang): string
    {
        $text = __($key, $words, $lang);

        return is_string($text) ? $text : $key;
    }
}
