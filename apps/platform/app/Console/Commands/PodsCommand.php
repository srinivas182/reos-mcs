<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Pods\Pods;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Every day: waiting agents matched into pods, and each pod's weekly call booked (A13.2). */
#[Signature('reos:pods')]
#[Description('Match waiting agents into pods and book each pod\'s weekly call (A13.2)')]
final class PodsCommand extends Command
{
    public function handle(Pods $pods): int
    {
        $done = $pods->run();
        $this->info("Matched {$done['matched']}, booked {$done['calls']} calls.");

        return self::SUCCESS;
    }
}
