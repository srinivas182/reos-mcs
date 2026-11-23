<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Licences\Journey\LicenceJourneys;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Daily (A10.3a): the licence exam a week and a day before, then a nudge to enter the result. */
#[Signature('reos:licence:remind')]
#[Description('Remind candidates of their licence exam and to enter the result')]
final class LicenceRemindCommand extends Command
{
    public function handle(LicenceJourneys $journeys): int
    {
        $this->components->info($journeys->remindDue().' exam reminder(s) sent.');

        return self::SUCCESS;
    }
}
