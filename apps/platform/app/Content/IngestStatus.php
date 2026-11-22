<?php

declare(strict_types=1);

namespace App\Content;

/** A lesson's knowledge base copy: its passages are rebuilt whenever its text or files change. */
enum IngestStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
