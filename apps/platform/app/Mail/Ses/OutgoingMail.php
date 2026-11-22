<?php

declare(strict_types=1);

namespace App\Mail\Ses;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * Every email just before it goes (B6.2d): addresses that bounced or
 * complained are taken off, and a message with nobody left isn't sent
 * (returning false stops Laravel sending it). Sent through SES, the From
 * address becomes the one verified in Settings › Integrations.
 */
final readonly class OutgoingMail
{
    public function __construct(private EmailSuppressions $suppressions, private SesMailer $ses) {}

    public function handle(MessageSending $event): bool
    {
        $message = $event->message;
        $keep = fn (array $addresses): array => array_values(array_filter($addresses, fn (Address $a): bool => ! $this->suppressions->suppressed($a->getAddress())));
        $to = $keep($message->getTo());
        $cc = $keep($message->getCc());
        $bcc = $keep($message->getBcc());

        if ($to === [] && $cc === [] && $bcc === []) {
            return false;
        }

        $message->getHeaders()->remove('To');
        $message->getHeaders()->remove('Cc');
        $message->getHeaders()->remove('Bcc');
        $message->to(...$to)->cc(...$cc)->bcc(...$bcc);

        $from = ($event->data['mailer'] ?? null) === SesMailer::MAILER ? $this->ses->fromAddress() : null;

        if ($from !== null) {
            $current = $message->getFrom();
            $message->from(new Address($from, $current === [] ? '' : $current[0]->getName()));
        }

        return true;
    }
}
