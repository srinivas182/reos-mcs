<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Social\SocialInsights;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Hourly: insights read back for posts from the last 30 days (B11). */
#[Signature('reos:social:insights')]
#[Description('Read insights back for recent social posts (B11)')]
final class SocialInsightsCommand extends Command
{
    public function handle(SocialInsights $insights): int
    {
        $this->info('Read insights for '.$insights->refresh().' posts.');

        return self::SUCCESS;
    }
}
