<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Content\Video\VideoHosts;
use App\Models\LessonMedia;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Fetches captions Mux generated for a lesson video (A5.3) and rebuilds the
 * lesson's passages, so what's said in the video is searchable with the
 * moment it's said.
 */
final class FetchVideoCaptions implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600, 1800];

    public function __construct(public readonly int $mediaId, public readonly string $trackId)
    {
        $this->onQueue('content');
    }

    public function handle(VideoHosts $videos): void
    {
        $media = LessonMedia::query()->find($this->mediaId);
        $mux = $videos->mux();

        if ($media === null || $mux === null || $media->mux_playback_id === null) {
            return;
        }

        $vtt = $mux->captions($media->mux_playback_id, $this->trackId);

        if ($vtt === null) {
            throw new RuntimeException('Mux captions are not ready yet.');
        }

        $media->forceFill(['transcript' => $vtt])->save();
        IngestLesson::dispatch($media->lesson_id);
    }
}
