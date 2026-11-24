<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\StudioVideo;
use App\Studio\Video\StudioVideos;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Renders a studio video in the queue worker (B12.2, ADR 0051): ffmpeg,
 * or the Simulator where there's none. One video renders at a time.
 */
final class RenderStudioVideo implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 900;

    public int $uniqueFor = 900;

    public function __construct(public readonly int $videoId)
    {
        $this->onQueue('content');
    }

    public function uniqueId(): string
    {
        return (string) $this->videoId;
    }

    public function handle(StudioVideos $videos): void
    {
        $video = StudioVideo::query()->with('user')->find($this->videoId);

        if ($video !== null && $video->status === 'rendering') {
            $videos->render($video);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $video = StudioVideo::query()->find($this->videoId);

        if ($video !== null) {
            app(StudioVideos::class)->failed($video);
        }
    }
}
