<?php

declare(strict_types=1);

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\LessonMedia;
use App\Services\Content\LessonFiles;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A lesson file on the local disk (development and demos), behind a
 * short-lived signed link from the lesson page. Answers range requests, so
 * the video can seek to a transcript line's moment. Object storage serves
 * its own signed links instead.
 */
final class LessonMediaController extends Controller
{
    public function __invoke(LessonMedia $media): BinaryFileResponse
    {
        $disk = LessonFiles::disk();
        abort_unless($disk->exists($media->path), 404);

        return response()->file($disk->path($media->path), [
            'Content-Type' => $media->mime,
            'Content-Disposition' => 'inline; filename="'.addcslashes($media->original_name, '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=600',
        ]);
    }
}
