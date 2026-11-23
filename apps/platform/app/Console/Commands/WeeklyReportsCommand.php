<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TestAttempt;
use App\Models\User;
use App\Notifications\WeeklyProgress;
use App\Practice\StudyPlans;
use App\Practice\WeeklyReports;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sunday evening (A9.2e): each candidate who studied in the last four
 * weeks gets the week's progress report by push and email (their
 * notification settings decide), once a week.
 */
#[Signature('reos:reports:weekly')]
#[Description('Sends each active candidate their weekly progress report')]
final class WeeklyReportsCommand extends Command
{
    public function handle(WeeklyReports $reports): int
    {
        $monday = WeeklyReports::weekOf(CarbonImmutable::now(StudyPlans::TIMEZONE));
        $since = $monday->subWeeks(3);
        $ids = TestAttempt::query()->where('submitted_at', '>=', $since->utc())->distinct()->pluck('user_id')
            ->merge(DB::table('seat_time_days')->where('day', '>=', $since->toDateString())->distinct()->pluck('user_id'))->unique()->values();
        $sent = 0;

        User::query()->whereIn('id', $ids)->whereNotNull('onboarded_at')->chunkById(200, function ($users) use ($reports, $monday, &$sent): void {
            foreach ($users as $user) {
                $user->notify(new WeeklyProgress($reports->for($user, $monday)));
                $sent++;
            }
        });

        $this->info("Sent {$sent} weekly reports.");

        return self::SUCCESS;
    }
}
