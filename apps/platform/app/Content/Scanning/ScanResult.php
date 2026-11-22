<?php

declare(strict_types=1);

namespace App\Content\Scanning;

/** What the virus scanner said about a file. */
enum ScanResult
{
    case Clean;
    case Infected;
    /** The scanner couldn't be reached or answered oddly: try again later. */
    case Unavailable;
}
