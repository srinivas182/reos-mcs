<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Connections\Exceptions\VendorRateLimited;
use App\Mls\MlsFeeds;
use App\Mls\MlsSync;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Replicates one MLS feed (B10.1), queued every 15 minutes on `connections`.
 * One at a time per MLS; a vendor's "slow down" waits as long as it asks;
 * failures are recorded on the feed and retried by the next run.
 */
final class SyncMlsFeed implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 840;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly string $integration)
    {
        $this->onQueue('connections');
    }

    public function uniqueId(): string
    {
        return $this->integration;
    }

    public function handle(MlsFeeds $feeds, MlsSync $sync): void
    {
        $definition = $feeds->find($this->integration);

        if ($definition === null) {
            return;
        }

        try {
            $sync->run($definition);
        } catch (VendorRateLimited $e) {
            $this->release($e->retryAfterSeconds);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('MLS feed sync failed', ['integration' => $this->integration, 'error' => $exception?->getMessage()]);
    }
}
