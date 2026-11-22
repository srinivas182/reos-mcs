<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Features\FlagWatch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Saves each flag's uses and errors and steps back any flag failing for 10 minutes (A6.2). */
#[Signature('reos:flags:watch')]
#[Description('Save feature flag error rates and roll back flags that keep failing')]
final class WatchFeatureFlags extends Command
{
    public function handle(FlagWatch $watch): int
    {
        $stepped = $watch->run();

        foreach ($stepped as $key) {
            $this->warn("Stepped back {$key}");
        }

        $this->info(count($stepped) === 0 ? 'No flag needed a rollback.' : count($stepped).' flag(s) stepped back.');

        return self::SUCCESS;
    }
}
