<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Crm\CsvImports;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Imports a mapped CSV of contacts (B8.3b), in the background. */
final class ImportContactsCsv implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(public readonly int $importId)
    {
        $this->onQueue('connections');
    }

    public function handle(CsvImports $imports): void
    {
        $imports->run($this->importId);
    }
}
