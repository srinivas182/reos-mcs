<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Teams\LeadRouting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Every minute: team leads nobody claimed in time go to the fallback (A17.3). */
#[Signature('reos:teams:leads')]
#[Description('Reassign team leads whose claim time ran out (A17.3)')]
final class LeadRoutingCommand extends Command
{
    public function handle(LeadRouting $routing): int
    {
        $this->info('Reassigned '.$routing->sweep().' leads.');

        return self::SUCCESS;
    }
}
