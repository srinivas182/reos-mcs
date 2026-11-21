<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Connections\Exceptions\VendorRateLimited;
use App\Connections\SyncTrigger;
use App\Models\Connection;
use App\Models\SyncRun;
use App\Services\Connections\ConnectionHealth;
use App\Services\Connections\SyncEngine;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

/**
 * Syncs one stream of one connection (ADR 0013). Queued on `connections`.
 *
 * - One at a time per connection and stream (unique until it starts).
 * - Fair: each owner (person or organisation) and each tool has its own
 *   per-minute allowance (`connection-owner`, `connection-vendor` in
 *   AppServiceProvider), so one brokerage can't hold up everyone else.
 * - Retries with backoff; a vendor's "slow down" waits as long as it asks.
 *   When retries run out, the connection's health records the failure.
 */
final class SyncConnection implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600, 1800];

    public function __construct(
        public readonly int $connectionId,
        public readonly string $stream,
        public readonly SyncTrigger $trigger,
    ) {
        $this->onQueue('connections');
    }

    public function uniqueId(): string
    {
        return "{$this->connectionId}:{$this->stream}";
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new RateLimited('connection-owner'), new RateLimited('connection-vendor')];
    }

    public function handle(SyncEngine $engine): void
    {
        $connection = Connection::query()->find($this->connectionId);

        if ($connection === null) {
            return;
        }

        try {
            $outcome = $engine->run($connection, $this->stream, $this->trigger);
        } catch (VendorRateLimited $e) {
            $this->release($e->retryAfterSeconds);

            return;
        }

        if ($outcome === SyncRun::OUTCOME_CONTINUED) {
            self::dispatch($this->connectionId, $this->stream, $this->trigger);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $connection = Connection::query()->find($this->connectionId);

        if ($connection !== null) {
            app(ConnectionHealth::class)->failed($connection, 'connections.errors.vendorUnavailable');
        }
    }
}
