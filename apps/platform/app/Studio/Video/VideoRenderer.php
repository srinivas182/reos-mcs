<?php

declare(strict_types=1);

namespace App\Studio\Video;

/**
 * Turns a studio video's plan into an MP4 (B12.2, ADR 0051). ffmpeg in the
 * queue worker renders it; the Simulator (CI, and machines without ffmpeg)
 * copies a short placeholder so everything around it can be tested.
 *
 * Every file in the plan is local, in the job's own folder: stills, clips
 * and the transparent overlays drawn by {@see VideoFrames}.
 *
 * @phpstan-type PlanScene array{kind: 'image'|'video', file: string, start: float, seconds: float, caption: string|null, sound: bool}
 * @phpstan-type Plan array{scenes: list<PlanScene>, frame: string, hook: string|null, hook_seconds: float, music: string}
 * @phpstan-type Rendered array{video: string, poster: string, seconds: float}
 * @phpstan-type Probe array{seconds: float|null, sound: bool}
 */
interface VideoRenderer
{
    /** ffmpeg or simulator. */
    public function engine(): string;

    /**
     * @param  Plan  $plan
     * @return Rendered files in $dir
     */
    public function render(array $plan, string $dir): array;

    /**
     * How long a clip is and whether it has sound.
     *
     * @return Probe
     */
    public function probe(string $file): array;
}
