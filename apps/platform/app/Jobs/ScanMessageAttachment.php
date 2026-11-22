<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Content\Scanning\FileScanner;
use App\Content\Scanning\ScanResult;
use App\Events\Messaging\MessageChanged;
use App\Messaging\Realtime;
use App\Models\MessageAttachment;
use App\Services\Content\LessonFiles;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Virus-scans a file sent with a message (B6.1b). Clean: it opens for the
 * conversation. Infected: the file is deleted and the message says so.
 * Scanner unavailable: tried again in a minute.
 */
final class ScanMessageAttachment implements ShouldQueue
{
    use Queueable;

    public int $tries = 30;

    public function __construct(public int $attachmentId) {}

    public function handle(FileScanner $scanner): void
    {
        $attachment = MessageAttachment::query()->with('message.conversation')->find($this->attachmentId);

        if ($attachment === null || $attachment->scan_status !== 'pending') {
            return;
        }

        $disk = LessonFiles::disk();
        $stream = $disk->readStream($attachment->path);

        if ($stream === null) {
            return;
        }

        try {
            $result = $scanner->scan($stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if ($result === ScanResult::Unavailable) {
            $this->release(60);

            return;
        }

        if ($result === ScanResult::Infected) {
            $disk->delete($attachment->path);
        }

        $attachment->forceFill(['scan_status' => $result === ScanResult::Clean ? 'clean' : 'infected'])->save();
        Realtime::send(new MessageChanged($attachment->message));
    }
}
