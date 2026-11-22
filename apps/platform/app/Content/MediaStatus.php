<?php

declare(strict_types=1);

namespace App\Content;

/** A lesson file's progress: uploaded, scanned, then processed (video on the host, slides read for text). */
enum MediaStatus: string
{
    case AwaitingUpload = 'awaiting_upload';
    case Processing = 'processing';
    case Ready = 'ready';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
