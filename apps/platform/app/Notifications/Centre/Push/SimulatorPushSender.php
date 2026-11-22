<?php

declare(strict_types=1);

namespace App\Notifications\Centre\Push;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

/**
 * Expo push on demo data (never in production): each push goes to the log
 * and a short outbox instead of a phone. A token containing "dead" answers
 * DeviceNotRegistered, like a phone that deleted the app.
 *
 * @phpstan-import-type PushMessage from PushSender
 */
final readonly class SimulatorPushSender implements PushSender
{
    public const OUTBOX = 'reos:push:outbox';

    public function __construct(private Cache $cache, private LoggerInterface $log) {}

    public function send(array $messages): array
    {
        $tickets = [];
        /** @var list<PushMessage> $outbox */
        $outbox = (array) $this->cache->get(self::OUTBOX, []);

        foreach ($messages as $message) {
            if (str_contains($message['to'], 'dead')) {
                $tickets[] = ['status' => 'error', 'error' => 'DeviceNotRegistered'];

                continue;
            }

            $this->log->info("[REOS demo] Push to {$message['to']}: {$message['title']}");
            array_unshift($outbox, $message);
            $tickets[] = ['status' => 'ok', 'id' => (string) Str::uuid()];
        }

        $this->cache->put(self::OUTBOX, array_slice($outbox, 0, 50), 86_400);

        return $tickets;
    }

    public function receipts(array $tickets): array
    {
        return array_fill_keys($tickets, null);
    }

    /** @return list<PushMessage> */
    public function outbox(): array
    {
        /** @var list<PushMessage> */
        return array_values((array) $this->cache->get(self::OUTBOX, []));
    }
}
