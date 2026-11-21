<?php

declare(strict_types=1);

namespace App\Connections;

/** A verified webhook: its unique key (for duplicate protection) and the connections to sync. */
final readonly class WebhookDelivery
{
    /** @param  list<int>  $connectionIds */
    public function __construct(
        public string $deliveryKey,
        public array $connectionIds,
        /** False for pings (for example Google's first "sync" message): acknowledged, nothing to do. */
        public bool $changed = true,
        /** The tool ended or is about to end its push channels (Microsoft's lifecycle notifications): open new ones. */
        public bool $channelsLost = false,
    ) {}
}
