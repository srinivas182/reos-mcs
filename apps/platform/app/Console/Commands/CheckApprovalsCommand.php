<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Approvals\CourseApprovals;
use App\Content\Avatars\AvatarConsents;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Course approvals (A7.1): reminders 60 and 30 days before expiry, and
 * expiries; and the samples of avatars whose consent ended (A7.1b).
 */
#[Signature('reos:approvals:check')]
#[Description('Remind schools of expiring course approvals and expire the ones past their date')]
final class CheckApprovalsCommand extends Command
{
    public function handle(CourseApprovals $approvals, AvatarConsents $avatars): int
    {
        $result = $approvals->check();
        $this->components->info("{$result['reminded']} reminder(s) sent, {$result['expired']} approval(s) expired.");
        $this->components->info($avatars->prune().' avatar sample(s) deleted.');

        return self::SUCCESS;
    }
}
