<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Deals\Deadlines;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Hourly (B9.1): pushes for deal key dates 72 and 24 hours away. */
#[Signature('reos:deals:deadlines')]
#[Description('Sends pushes for deal key dates 72 and 24 hours away')]
final class DealDeadlinesCommand extends Command
{
    public function handle(Deadlines $deadlines): int
    {
        $this->components->info($deadlines->check().' deadline pushes sent.');

        return self::SUCCESS;
    }
}
