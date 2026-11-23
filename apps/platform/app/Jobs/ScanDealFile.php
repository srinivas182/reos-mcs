<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Content\Scanning\FileScanner;
use App\Content\Scanning\ScanResult;
use App\Models\DealFile;
use App\Services\Content\LessonFiles;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Virus-scans a file kept on a deal (B9.3): an upload, or a signed copy
 * and certificate from DocuSign. Clean: it opens and can be sent.
 * Infected: the file is deleted. Scanner unavailable: tried again in a
 * minute.
 */
final class ScanDealFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 30;

    public function __construct(public int $fileId) {}

    public function handle(FileScanner $scanner): void
    {
        $file = DealFile::query()->find($this->fileId);

        if ($file === null || $file->scan_status !== 'pending') {
            return;
        }

        $disk = LessonFiles::disk();
        $stream = $disk->readStream($file->path);

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
            $disk->delete($file->path);
        }

        $file->forceFill(['scan_status' => $result === ScanResult::Clean ? 'clean' : 'infected'])->save();
    }
}
