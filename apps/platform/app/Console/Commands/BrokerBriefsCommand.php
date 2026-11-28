<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Brokerage\BrokerBriefs;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Weekday mornings: brokers with something waiting get "Your morning brief is ready" (A16.3). */
#[Signature('reos:brokerage:briefs')]
#[Description('Tell each broker their morning brief is ready (A16.3)')]
final class BrokerBriefsCommand extends Command
{
    public function handle(BrokerBriefs $briefs): int
    {
        $this->info('Sent '.$briefs->sendDue().' broker briefs.');

        return self::SUCCESS;
    }
}
