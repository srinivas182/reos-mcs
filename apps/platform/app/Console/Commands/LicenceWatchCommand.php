<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Licences\Journey\SwitchToAgent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Daily (A10.3c): licence numbers that weren't active yet; the candidate is told when one is. */
#[Signature('reos:licence:watch')]
#[Description('Check entered licence numbers that are not active yet, and tell candidates when they are')]
final class LicenceWatchCommand extends Command
{
    public function handle(SwitchToAgent $switch): int
    {
        $this->components->info($switch->watchDue().' licence(s) now active.');

        return self::SUCCESS;
    }
}
