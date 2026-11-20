<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Integrations\HealthMonitor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Scheduled every 15 minutes (routes/console.php). */
#[Signature('reos:integrations:health')]
#[Description('Check integrations running on vendor systems and pause any that keep failing')]
final class IntegrationsHealthCommand extends Command
{
    public function handle(HealthMonitor $monitor): int
    {
        $summary = $monitor->run();

        $this->components->info("Checked {$summary['checked']}, failed {$summary['failed']}, paused {$summary['paused']}.");

        return self::SUCCESS;
    }
}
