<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Brokerage\Sponsorships;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reos:sponsorships')]
#[Description('End agents\' roles on their release date and confirm joins and releases against TREC\'s file (A16.2)')]
final class SponsorshipsCommand extends Command
{
    public function handle(Sponsorships $sponsorships): int
    {
        [$ended, $confirmed] = $sponsorships->run();
        $this->components->info("{$ended} releases took effect; {$confirmed} changes confirmed with TREC.");

        return self::SUCCESS;
    }
}
