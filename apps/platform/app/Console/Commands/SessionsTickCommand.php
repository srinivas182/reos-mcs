<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Video\Classes\ClassRoom;
use App\Video\Classes\LiveClasses;
use App\Video\VideoSessions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Every minute (B6.3): closes rooms whose booked end has come, for everyone,
 * and sends "starting in 10 minutes"; for live classes (A10.2b) also "starts
 * in an hour", attendance checks on the approval's interval and their
 * one reminder.
 */
#[Signature('reos:sessions:tick')]
#[Description('End video sessions at their booked end and remind people of ones starting soon')]
final class SessionsTickCommand extends Command
{
    public function handle(VideoSessions $sessions, LiveClasses $classes, ClassRoom $room): int
    {
        $checks = $room->tick();
        $ended = $sessions->endDue();
        $reminded = $sessions->remindDue() + $classes->remindDue();
        $this->components->info("{$ended} session(s) ended, {$reminded} reminder(s) sent, {$checks['opened']} attendance check(s) opened, {$checks['reminded']} check reminder(s) sent.");

        return self::SUCCESS;
    }
}
