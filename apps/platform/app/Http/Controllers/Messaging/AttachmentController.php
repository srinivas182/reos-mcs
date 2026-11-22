<?php

declare(strict_types=1);

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Models\MessageAttachment;
use App\Services\Content\LessonFiles;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A file sent with a message (B6.1b), on the local disk, through the
 * short-lived signed link the conversation gives its participants. Object
 * storage signs its own links instead. Only scanned, clean files open.
 */
final class AttachmentController extends Controller
{
    public function __invoke(MessageAttachment $attachment): BinaryFileResponse
    {
        $disk = LessonFiles::disk();
        abort_unless($attachment->scan_status === 'clean' && $disk->exists($attachment->path), 404);

        return response()->file($disk->path($attachment->path), [
            'Content-Type' => $attachment->mime,
            'Content-Disposition' => 'inline; filename="'.addcslashes($attachment->name, '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, max-age=600',
        ]);
    }
}
