<?php

declare(strict_types=1);

namespace App\Content\Video;

use App\Content\MediaStatus;
use App\Models\LessonMedia;

/**
 * The Simulator (outside production): a video is "ready" at once and plays
 * from its original file. There are no generated captions; upload a
 * captions file (.vtt) to make the video's words searchable.
 */
final class SimulatedVideoHost implements VideoHost
{
    public function start(LessonMedia $media, string $sourceUrl, string $language): void
    {
        $media->forceFill([
            'mux_asset_id' => 'sim-asset-'.$media->id,
            'status' => MediaStatus::Ready,
        ])->save();
    }

    public function remove(LessonMedia $media): void {}
}
