<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\LessonMedia;
use App\Services\Content\LessonFiles;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Scans an uploaded lesson file for viruses, then processes it (A5.3):
 * video to the video host, slides and documents read for their words,
 * captions read for their lines. The lesson's passages are rebuilt after.
 */
final class ProcessLessonMedia implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 900;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $mediaId)
    {
        $this->onQueue('content');
    }

    public function uniqueId(): string
    {
        return (string) $this->mediaId;
    }

    public function handle(LessonFiles $files): void
    {
        $media = LessonMedia::query()->find($this->mediaId);

        if ($media !== null) {
            $files->process($media, $this->attempts() < $this->tries ? fn (int $seconds) => $this->release($seconds) : null);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(LessonFiles::class)->failed($this->mediaId, 'content.files.failed.processing');
    }
}
