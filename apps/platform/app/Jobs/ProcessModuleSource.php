<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Content\Builder\ModuleSources;
use App\Models\ModuleSource;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Scans a module's source file for viruses, then reads it (A7.2). */
final class ProcessModuleSource implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 900;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $sourceId)
    {
        $this->onQueue('content');
    }

    public function uniqueId(): string
    {
        return (string) $this->sourceId;
    }

    public function handle(ModuleSources $sources): void
    {
        $source = ModuleSource::query()->find($this->sourceId);

        if ($source !== null) {
            $sources->process($source, $this->attempts() < $this->tries ? fn (int $seconds) => $this->release($seconds) : null);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(ModuleSources::class)->failed($this->sourceId, 'content.files.failed.processing');
    }
}
