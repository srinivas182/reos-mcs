<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\StudyPlan;
use App\Models\TestAttempt;
use App\Models\User;
use App\Practice\Readiness;
use App\Practice\StudyPlans;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Nightly readiness (A9.2, ADR 0035), just after midnight on candidates'
 * time: keeps each active candidate's readiness for the day (older answers
 * now count a little less) and, when the weak topics changed, rebuilds the
 * study plan so tomorrow follows readiness.
 */
#[Signature('reos:readiness:nightly')]
#[Description('Records each candidate\'s readiness for the day and rebuilds plans whose weak topics changed')]
final class ReadinessNightlyCommand extends Command
{
    public function handle(Readiness $readiness, StudyPlans $plans): int
    {
        $recorded = 0;
        $rebuilt = 0;
        $ids = TestAttempt::query()->whereNotNull('submitted_at')->where('submitted_at', '>=', now()->subDays(120))->distinct()->pluck('user_id');

        User::query()->whereIn('id', $ids)->whereNotNull('onboarded_at')->chunkById(200, function ($users) use ($readiness, $plans, &$recorded, &$rebuilt): void {
            foreach ($users as $user) {
                $snapshot = $readiness->record($user);
                $recorded++;
                $plan = StudyPlan::query()->where('user_id', $user->id)->where('active', true)->latest('id')->first();

                if ($plan !== null && $plan->weak_topics !== $snapshot->weakest) {
                    $plans->rebuild($user, 'readiness');
                    $rebuilt++;
                }
            }
        });

        $this->info("Recorded readiness for {$recorded} candidates; rebuilt {$rebuilt} plans.");

        return self::SUCCESS;
    }
}
