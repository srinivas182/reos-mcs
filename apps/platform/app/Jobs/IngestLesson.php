<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Content\Knowledge\Ingestion;
use App\Models\Lesson;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Rebuilds one lesson's knowledge base passages (A5.3) on the `content`
 * queue, after its text or files change. Queued once at a time per lesson;
 * a change during a run queues another.
 */
final class IngestLesson implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $lessonId)
    {
        $this->onQueue('content');
    }

    public function uniqueId(): string
    {
        return (string) $this->lessonId;
    }

    public function handle(Ingestion $ingestion): void
    {
        $lesson = Lesson::query()->find($this->lessonId);

        if ($lesson !== null) {
            $ingestion->ingest($lesson);
        }
    }
}
