<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A referral to an agent who isn't on REOS (B14.3b): the client's details
 * and the fee the sender proposes, by email in the sender's language. The
 * receiving agent replies to the sender directly.
 */
final class ReferralEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Referral $referral, public readonly User $from) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->referral;
        $lang = $this->from->locale->value;
        $words = [
            'from' => (string) $this->from->name, 'broker' => $r->from_broker ?? '—', 'name' => $r->to_name, 'client' => $r->client_name,
            'kind' => self::tr('leads.referrals.kinds.'.$r->kind, [], $lang), 'area' => $r->area ?? '—', 'fee' => $r->fee_percent === null ? '—' : $r->fee_percent.'%',
            'contact' => implode(' · ', array_filter([$r->client_email, $r->client_phone])), 'note' => $r->note ?? '',
        ];
        $mail = (new MailMessage)
            ->subject(self::tr('leads.referrals.mail.subject', $words, $lang))
            ->greeting(self::tr('leads.referrals.mail.greeting', $words, $lang))
            ->line(self::tr('leads.referrals.mail.body', $words, $lang))
            ->line(self::tr('leads.referrals.mail.client', $words, $lang))
            ->line(self::tr('leads.referrals.mail.fee', $words, $lang));

        if ($r->note !== null) {
            $mail->line(self::tr('leads.referrals.mail.note', $words, $lang));
        }

        if ($this->from->email !== null) {
            $mail->replyTo($this->from->email, (string) $this->from->name);
        }

        return $mail->line(self::tr('leads.referrals.mail.reply', $words, $lang));
    }

    /** @param array<string, string> $words */
    private static function tr(string $key, array $words, string $lang): string
    {
        $text = __($key, $words, $lang);

        return is_string($text) ? $text : $key;
    }
}
