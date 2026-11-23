<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Content\Scanning\FileScanner;
use App\Content\Scanning\ScanResult;
use App\Models\CeCompletion;
use App\Models\CertificateRead;
use App\Services\Content\LessonFiles;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Virus-scans an uploaded certificate (B10.3b). Infected: the file is
 * deleted, and a completion saved with it loses the link. Scanner
 * unavailable: tried again in a minute.
 */
final class ScanCertificate implements ShouldQueue
{
    use Queueable;

    public int $tries = 30;

    public function __construct(public string $path) {}

    public function handle(FileScanner $scanner): void
    {
        $disk = LessonFiles::disk();
        $stream = $disk->readStream($this->path);

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
            $disk->delete($this->path);
            CeCompletion::query()->where('certificate_path', $this->path)->update(['certificate_path' => null]);
        }

        CertificateRead::query()->where('path', $this->path)->update(['scan_status' => $result === ScanResult::Clean ? 'clean' : 'infected']);
    }
}
