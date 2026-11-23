<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Connections\Exceptions\VendorRateLimited;
use App\Crm\CrmWriteBack;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Sends one REOS change (a note, a stage, a task done, a new task or person) to the contact's CRMs (B7.2, B8.3). Retried with backoff. */
final class PushToCrm implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public readonly string $kind, public readonly int $id, public readonly ?int $target = null)
    {
        $this->onQueue('connections');
    }

    public function handle(CrmWriteBack $writeBack): void
    {
        try {
            $writeBack->push($this->kind, $this->id, $this->target);
        } catch (VendorRateLimited $e) {
            $this->release($e->retryAfterSeconds);
        }
    }
}
