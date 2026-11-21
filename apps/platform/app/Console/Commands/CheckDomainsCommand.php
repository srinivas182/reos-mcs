<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tenancy\TenantDomains;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Re-checks tenants' domains still waiting for DNS or SSL (A4.2b), every 10 minutes. */
#[Signature('reos:domains:check')]
#[Description('Check tenant custom domains that are waiting for DNS or SSL')]
final class CheckDomainsCommand extends Command
{
    public function handle(TenantDomains $domains): int
    {
        $this->components->info($domains->checkWaiting().' waiting domains checked.');

        return self::SUCCESS;
    }
}
