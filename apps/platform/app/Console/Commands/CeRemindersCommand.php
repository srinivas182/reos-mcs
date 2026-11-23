<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Licences\Ce\CeReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Daily: CE reminders 60, 30 and 7 days before renewal, and "Renewal ready" (A11.2). */
#[Signature('reos:ce:reminders')]
#[Description('Send CE renewal reminders and "Renewal ready" (A11.2)')]
final class CeRemindersCommand extends Command
{
    public function handle(CeReminders $reminders): int
    {
        $this->info('Sent '.$reminders->sendDue().' CE reminders.');

        return self::SUCCESS;
    }
}
