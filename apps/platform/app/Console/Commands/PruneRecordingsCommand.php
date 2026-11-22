<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Video\SessionRecordings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Deletes session recordings 90 days after the session (B6.3c; decision of 22 November 2026). */
#[Signature('reos:recordings:prune')]
#[Description('Delete session recordings past their 90 days')]
final class PruneRecordingsCommand extends Command
{
    public function handle(SessionRecordings $recordings): int
    {
        $this->components->info($recordings->prune().' recording(s) deleted.');

        return self::SUCCESS;
    }
}
