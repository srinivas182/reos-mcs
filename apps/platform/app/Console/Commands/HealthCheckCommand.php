<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Monitoring\HealthChecks;
use App\Monitoring\RequestMetrics;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reos:health:check')]
#[Description('Check the database, cache, queues and storage, and save the last minutes\' request metrics (A6.1)')]
final class HealthCheckCommand extends Command
{
    public function handle(HealthChecks $health, RequestMetrics $metrics): int
    {
        $result = $health->run();
        $metrics->flush();
        $this->line($result['ok'] ? 'Healthy' : 'Unhealthy: '.implode(', ', array_keys(array_filter($result['parts'], static fn (array $p): bool => ! $p['ok']))));

        return self::SUCCESS;
    }
}
