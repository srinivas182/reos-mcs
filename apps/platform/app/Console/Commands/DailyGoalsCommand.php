<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Rewards\DailyGoals;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Every night: yesterday's daily goals settled for every agent, so points and streaks count (A13.3a). */
#[Signature('reos:daily-goals')]
#[Description('Settle yesterday\'s daily goals and points for every agent (A13.3a)')]
final class DailyGoalsCommand extends Command
{
    public function handle(DailyGoals $goals): int
    {
        $this->info('Settled '.$goals->run().' agents.');

        return self::SUCCESS;
    }
}
