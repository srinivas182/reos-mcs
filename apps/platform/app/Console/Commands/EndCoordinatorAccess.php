<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Coordinators\DealShares;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Daily backstop (B17.1): coordinator access ends on deals that closed or were cancelled. */
#[Signature('reos:coordinators:sweep')]
#[Description('End coordinator access on closed or cancelled deals (B17.1)')]
final class EndCoordinatorAccess extends Command
{
    public function handle(DealShares $shares): int
    {
        $this->info('Ended '.$shares->sweep().' coordinator shares.');

        return self::SUCCESS;
    }
}
