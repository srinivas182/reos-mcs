<?php

declare(strict_types=1);

namespace App\Content\Scanning;

/**
 * Used outside production when no ClamAV is set (local machines and CI):
 * files are accepted unscanned. Production never uses it; there, uploads
 * wait until a scanner is set.
 */
final class UnscannedOutsideProduction implements FileScanner
{
    public function scan($stream): ScanResult
    {
        return ScanResult::Clean;
    }
}
