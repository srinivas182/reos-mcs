<?php

declare(strict_types=1);

namespace App\Connections;

use App\Models\Connection;

/** Drivers whose tool can push "something changed" to REOS's webhook address. */
interface SubscribesToChanges
{
    /**
     * Ask the tool to send changes to `$webhookUrl`.
     *
     * @return array<string, mixed> What to remember in the connection's `meta` (channel ids, expiry).
     */
    public function subscribe(Connection $connection, string $accessToken, string $webhookUrl): array;
}
