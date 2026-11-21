<?php

declare(strict_types=1);

namespace App\Messaging;

use Illuminate\Contracts\Cache\Repository as Cache;
use Psr\Log\LoggerInterface;

/**
 * Twilio SMS on demo data (never in production): the text goes to the log
 * and a short outbox instead of a phone, so invites by text can be tried
 * before the Twilio number and 10DLC registration exist.
 */
final readonly class SimulatorTextMessages implements TextMessages
{
    public const OUTBOX = 'reos:sms:outbox';

    public function __construct(private Cache $cache, private LoggerInterface $log) {}

    public function send(string $to, string $body): void
    {
        $this->log->info("[REOS demo] Text to {$to}: {$body}");

        /** @var list<array{to: string, body: string, at: string}> $outbox */
        $outbox = (array) $this->cache->get(self::OUTBOX, []);
        array_unshift($outbox, ['to' => $to, 'body' => $body, 'at' => now()->toIso8601String()]);
        $this->cache->put(self::OUTBOX, array_slice($outbox, 0, 50), 86_400);
    }

    /** @return list<array{to: string, body: string, at: string}> */
    public function outbox(): array
    {
        /** @var list<array{to: string, body: string, at: string}> */
        return array_values((array) $this->cache->get(self::OUTBOX, []));
    }
}
