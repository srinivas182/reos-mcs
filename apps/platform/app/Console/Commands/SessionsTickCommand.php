<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Video\VideoSessions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Every minute (B6.3): closes rooms whose booked end has come, for everyone,
 * and sends "starting in 10 minutes".
 */
#[Signature('reos:sessions:tick')]
#[Description('End video sessions at their booked end and remind people of ones starting soon')]
final class SessionsTickCommand extends Command
{
    public function handle(VideoSessions $sessions): int
    {
        $ended = $sessions->endDue();
        $reminded = $sessions->remindDue();
        $this->components->info("{$ended} session(s) ended, {$reminded} reminder(s) sent.");

        return self::SUCCESS;
    }
}
