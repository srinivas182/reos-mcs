<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Agents\AgentBriefs;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Every five minutes: agents whose brief time has come get "Your morning brief is ready" (A11.1c). */
#[Signature('reos:agents:briefs')]
#[Description('Send each agent their morning brief notification at their chosen time (A11.1c)')]
final class AgentBriefsCommand extends Command
{
    public function handle(AgentBriefs $briefs): int
    {
        $this->info('Sent '.$briefs->sendDue().' morning briefs.');

        return self::SUCCESS;
    }
}
