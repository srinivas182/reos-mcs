<?php

declare(strict_types=1);

namespace App\Content\Video;

use App\Models\LessonMedia;

/** Where lesson videos stream from (A5.3, decision 2: Mux). */
interface VideoHost
{
    /**
     * Hands a scanned video to the host, which copies it from a short-lived
     * signed link and asks for captions in the lesson's language. The media
     * becomes Ready when the host says so (webhook) or at once (Simulator).
     */
    public function start(LessonMedia $media, string $sourceUrl, string $language): void;

    /** Removes the video from the host (the lesson file was deleted). */
    public function remove(LessonMedia $media): void;
}
