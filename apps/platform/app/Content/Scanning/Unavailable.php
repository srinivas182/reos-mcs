<?php

declare(strict_types=1);

namespace App\Content\Scanning;

/** Production with no scanner set: nothing is accepted until one is. */
final class Unavailable implements FileScanner
{
    public function scan($stream): ScanResult
    {
        return ScanResult::Unavailable;
    }
}
