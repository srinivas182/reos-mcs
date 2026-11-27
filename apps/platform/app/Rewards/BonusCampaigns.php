<?php

declare(strict_types=1);

namespace App\Rewards;

use App\Authorization\Role;
use App\Mentoring\MentorDirectory;
use App\Models\Organisation;
use App\Models\PointsCampaign;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Bonus campaigns a brokerage funds (A13.3a), such as double points on CE
 * in November. The extra points come from the brokerage's points account,
 * never from REOS's, and stop when the campaign's budget is spent.
 *
 * @phpstan-type CampaignRow array{id: string, title: string, goal: string, multiplier: int, starts_on: string, ends_on: string, budget: int, spent: int, active: bool, closed: bool}
 */
final readonly class BonusCampaigns
{
    public const MULTIPLIERS = [2, 3];

    public const MAX_DAYS = 92;

    public function __construct(private MentorDirectory $directory, private AuditLogger $audit) {}

    public function create(User $broker, string $title, string $goal, int $multiplier, string $startsOn, string $endsOn, int $budget): PointsCampaign
    {
        $brokerage = $this->directory->brokerageOf($broker, Role::Broker) ?? throw new NotFoundHttpException;
        $starts = CarbonImmutable::parse($startsOn);
        $ends = CarbonImmutable::parse($endsOn);

        if ($ends->lt($starts) || $ends->lt(CarbonImmutable::today()) || $starts->diffInDays($ends) > self::MAX_DAYS) {
            throw ValidationException::withMessages(['ends_on' => __('rewards.errors.dates')]);
        }

        if ($goal !== 'all' && ! in_array($goal, PointRates::GOALS, true)) {
            throw ValidationException::withMessages(['goal' => __('rewards.errors.goal')]);
        }

        $campaign = PointsCampaign::query()->create([
            'uuid' => (string) Str::uuid7(), 'organisation_id' => $brokerage->id, 'title' => mb_substr(trim($title), 0, 120), 'goal' => $goal,
            'multiplier' => in_array($multiplier, self::MULTIPLIERS, true) ? $multiplier : 2, 'starts_on' => $starts->toDateString(), 'ends_on' => $ends->toDateString(),
            'budget' => max(1, $budget), 'created_by' => $broker->id,
        ]);
        $this->audit->record('points_campaign.created', 'points_campaign', $campaign->uuid, ['title' => $campaign->title, 'goal' => $goal, 'multiplier' => $campaign->multiplier, 'budget' => $campaign->budget, 'starts_on' => $campaign->starts_on->toDateString(), 'ends_on' => $campaign->ends_on->toDateString()], $broker, $brokerage->id);

        return $campaign;
    }

    /** Ends a campaign now: no more bonus points (the budget closes at what was spent). */
    public function end(User $broker, string $uuid): void
    {
        $brokerage = $this->directory->brokerageOf($broker, Role::Broker) ?? throw new NotFoundHttpException;
        $campaign = PointsCampaign::query()->where('organisation_id', $brokerage->id)->where('uuid', $uuid)->firstOrFail();
        $today = CarbonImmutable::today();

        if ($campaign->ends_on->gte($today) && $campaign->spent < $campaign->budget) {
            $before = ['ends_on' => $campaign->ends_on->toDateString(), 'budget' => $campaign->budget];
            $campaign->forceFill(['ends_on' => $campaign->starts_on->gt($today) ? $campaign->starts_on : $today, 'budget' => $campaign->spent])->save();
            $this->audit->record('points_campaign.ended', 'points_campaign', $campaign->uuid, ['before' => $before, 'after' => ['ends_on' => $campaign->ends_on->toDateString(), 'budget' => $campaign->budget]], $broker, $brokerage->id);
        }
    }

    /** @return list<CampaignRow> */
    public function list(Organisation $brokerage): array
    {
        $today = CarbonImmutable::today();

        return array_values(PointsCampaign::query()->where('organisation_id', $brokerage->id)->whereDate('ends_on', '>=', $today->subDays(90)->toDateString())->orderByDesc('starts_on')->get()
            ->map(static fn (PointsCampaign $c): array => [
                'id' => $c->uuid, 'title' => $c->title, 'goal' => $c->goal, 'multiplier' => $c->multiplier,
                'starts_on' => $c->starts_on->toDateString(), 'ends_on' => $c->ends_on->toDateString(), 'budget' => $c->budget, 'spent' => $c->spent,
                'active' => $c->starts_on->lte($today) && $c->ends_on->gte($today) && $c->spent < $c->budget,
                // Ended, or its budget is used up.
                'closed' => $c->ends_on->lt($today) || $c->spent >= $c->budget,
            ])->all());
    }
}
