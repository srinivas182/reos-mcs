<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ProviderNetwork\ProviderRequests;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Daily: requests to connect unanswered for 14 days expire (B16.1). */
#[Signature('reos:providers:requests')]
#[Description('Expire requests to connect that went unanswered for 14 days (B16.1)')]
final class ProviderRequestsCommand extends Command
{
    public function handle(ProviderRequests $requests): int
    {
        $this->info('Expired '.$requests->expire().' requests.');

        return self::SUCCESS;
    }
}
