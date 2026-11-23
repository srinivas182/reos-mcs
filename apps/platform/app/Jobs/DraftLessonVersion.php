<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Content\Ai\LessonPipeline;
use App\Models\LessonVersion;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Drafts an AI lesson version: script, translation, legal checks and review items, then the renders (A7.3). */
final class DraftLessonVersion implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $versionId, public readonly int $checkinMinutes)
    {
        $this->onQueue('content');
    }

    public function uniqueId(): string
    {
        return (string) $this->versionId;
    }

    public function handle(LessonPipeline $pipeline): void
    {
        $version = LessonVersion::query()->find($this->versionId);

        if ($version !== null) {
            $pipeline->draft($version, $this->checkinMinutes);
        }
    }
}
