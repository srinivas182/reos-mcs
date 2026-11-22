<?php

declare(strict_types=1);

namespace App\Content\Scanning;

/** Scans an uploaded file before anyone can open it (A5.3). */
interface FileScanner
{
    /** @param  resource  $stream */
    public function scan($stream): ScanResult;
}
