<?php

declare(strict_types=1);

namespace App\Studio\Video;

/**
 * The Simulator for studio videos (B12.2, ADR 0051): CI and machines
 * without ffmpeg get a one-second placeholder MP4 and a real poster, so
 * making, editing, checking and posting a video can all be tested. The
 * length reported is the plan's.
 *
 * @phpstan-import-type Plan from VideoRenderer
 */
final class SimulatedRenderer implements VideoRenderer
{
    public function engine(): string
    {
        return 'simulator';
    }

    public function render(array $plan, string $dir): array
    {
        copy(resource_path('studio/placeholder.mp4'), "{$dir}/video.mp4");
        $first = $plan['scenes'][0] ?? null;
        $overlays = array_values(array_filter([$plan['frame'], $plan['hook'], $first['caption'] ?? null]));
        VideoFrames::poster($first !== null && $first['kind'] === 'image' ? $first['file'] : '', $overlays, "{$dir}/poster.jpg");

        return ['video' => "{$dir}/video.mp4", 'poster' => "{$dir}/poster.jpg", 'seconds' => round(array_sum(array_column($plan['scenes'], 'seconds')), 1)];
    }

    public function probe(string $file): array
    {
        return ['seconds' => null, 'sound' => false];
    }
}
