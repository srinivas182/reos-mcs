<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\DailyGoalDay;
use App\Models\Organisation;
use App\Models\PointsCampaign;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Goals and rewards (A13.3a): Lone Star Realty runs "Double points on CE
 * this month" with a 50,000-point budget, and James Miller is on a
 * four-day streak of meeting his daily goals.
 */
final readonly class DemoRewards
{
    public function seed(): void
    {
        $lone = Organisation::query()->where('name', 'Lone Star Realty')->first();
        $linda = User::query()->where('email', 'linda.park@'.Personas::DOMAIN)->first();
        $james = User::query()->where('email', 'james.miller@'.Personas::DOMAIN)->first();

        if ($lone === null || $linda === null || $james === null || PointsCampaign::query()->where('organisation_id', $lone->id)->exists()) {
            return;
        }

        $today = CarbonImmutable::today('America/Chicago');
        PointsCampaign::query()->create([
            'uuid' => (string) Str::uuid7(), 'organisation_id' => $lone->id, 'title' => 'Double points on CE this month', 'goal' => 'ce_lesson', 'multiplier' => 2,
            'starts_on' => $today->startOfMonth()->toDateString(), 'ends_on' => $today->endOfMonth()->toDateString(), 'budget' => 50000, 'created_by' => $linda->id,
        ]);

        foreach (range(1, 4) as $back) {
            DailyGoalDay::query()->firstOrCreate(['user_id' => $james->id, 'day' => $today->subDays($back)->toDateString()], [
                'mode' => 'launch', 'met' => 4, 'total' => 4, 'points' => 100, 'completed' => true,
                'goals' => [
                    ['key' => 'conversations', 'done' => 6, 'target' => 5, 'met' => true],
                    ['key' => 'ce_lesson', 'done' => 1, 'target' => 1, 'met' => true],
                    ['key' => 'follow_ups', 'done' => 3, 'target' => 3, 'met' => true],
                    ['key' => 'post', 'done' => 1, 'target' => 1, 'met' => true],
                ],
            ]);
        }
    }
}
