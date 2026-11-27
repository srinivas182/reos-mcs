<?php

declare(strict_types=1);

namespace App\Rewards;

use App\Agents\Activity;
use App\Agents\AgentModes;
use App\Authorization\Role;
use App\Billing\Ledger\Points;
use App\Mentoring\MentorDirectory;
use App\Models\AgentProfile;
use App\Models\DailyGoalDay;
use App\Models\DailyGoalSetting;
use App\Models\LessonProgress;
use App\Models\Organisation;
use App\Models\PointsCampaign;
use App\Models\Todo;
use App\Models\User;
use App\Notifications\Centre\NotificationCentre;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Daily goals and points (A13.3a, ADR 0055; agent screen 111).
 *
 * Each mode has a few goals for the day — conversations logged in the CRM,
 * a CE lesson, follow-ups done, one post — with REOS's targets or the
 * brokerage's own. Meeting a goal earns its points once (the day's total is
 * capped), meeting all of them earns a bonus and keeps the streak going,
 * and a brokerage campaign can multiply the points it funds. Everything
 * counted is the agent's own activity; nothing is earned from a service
 * provider.
 *
 * @phpstan-type GoalRow array{key: string, done: float|int, target: int, points: int, met: bool, bonus: int}
 * @phpstan-type Today array{day: string, mode: string, goals: list<GoalRow>, points_today: int, bonus_today: int, all_done: bool, all_done_points: int, streak: int, best_streak: int, balance: int, campaigns: list<array{id: string, title: string, goal: string, multiplier: int, ends_on: string}>}
 */
final readonly class DailyGoals
{
    public function __construct(
        private PointRates $rates,
        private AgentModes $modes,
        private MentorDirectory $directory,
        private NotificationCentre $centre,
        private Points $points,
        private AuditLogger $audit,
    ) {}

    /**
     * Today's goals, awarding what's been met so far.
     *
     * @return Today
     */
    public function today(User $agent, ?CarbonImmutable $now = null): array
    {
        $zone = $this->centre->settings($agent)->timezone;
        $local = ($now ?? CarbonImmutable::now())->setTimezone($zone);
        $row = $this->settle($agent, $local->toDateString());
        $brokerage = $this->directory->brokerageOf($agent);
        $bonus = [];

        foreach ($row->goals as $goal) {
            $bonus[$goal['key']] = 0;
        }

        foreach ($brokerage === null ? [] : $this->campaigns($brokerage, $row->day) as $campaign) {
            foreach ($row->goals as $goal) {
                if ($goal['met'] && ($campaign->goal === 'all' || $campaign->goal === $goal['key'])) {
                    $bonus[$goal['key']] += $this->goalPoints($goal['key']) * ($campaign->multiplier - 1);
                }
            }
        }

        [$streak, $best] = $this->streaks($agent, $row->day);
        $rates = $this->rates->current();

        return [
            'day' => $row->day,
            'mode' => $row->mode,
            'goals' => array_map(static fn (array $g): array => [...$g, 'points' => $rates['points'][$g['key']] ?? 0, 'bonus' => $bonus[$g['key']] ?? 0], $row->goals),
            'points_today' => $row->points,
            'bonus_today' => $row->bonus,
            'all_done' => $row->completed,
            'all_done_points' => $rates['all_done'],
            'streak' => $streak,
            'best_streak' => $best,
            'balance' => $this->points->balance($agent),
            'campaigns' => array_map(static fn (PointsCampaign $c): array => ['id' => $c->uuid, 'title' => $c->title, 'goal' => $c->goal, 'multiplier' => $c->multiplier, 'ends_on' => $c->ends_on->toDateString()],
                $brokerage === null ? [] : $this->campaigns($brokerage, $row->day)),
        ];
    }

    /**
     * Counts one day (Y-m-d in the agent's time zone) and awards what was met,
     * once. The nightly run settles yesterday for everyone.
     */
    public function settle(User $agent, string $day): DailyGoalDay
    {
        $zone = $this->centre->settings($agent)->timezone;
        $from = CarbonImmutable::parse($day, $zone)->startOfDay();
        $to = $from->addDay();
        $mode = $this->modes->goalOf($agent);
        $brokerage = $this->directory->brokerageOf($agent);
        $rates = $this->rates->current();
        $goals = [];

        foreach ($this->targets($mode, $brokerage) as $key => $target) {
            if ($target <= 0) {
                continue;
            }
            $done = self::done($key, $agent, $from, $to);
            $goals[] = ['key' => $key, 'done' => $done, 'target' => $target, 'met' => $done >= $target];
        }

        return DB::transaction(function () use ($agent, $day, $mode, $goals, $rates, $brokerage): DailyGoalDay {
            $row = DailyGoalDay::query()->firstOrCreate(['user_id' => $agent->id, 'day' => $day], ['mode' => $mode, 'goals' => $goals]);
            $row = DailyGoalDay::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            $earned = $row->points;
            $bonus = $row->bonus;
            $met = array_values(array_filter($goals, static fn (array $g): bool => $g['met']));
            $all = $goals !== [] && count($met) === count($goals);
            $awards = array_map(static fn (array $g): array => [$g['key'], $rates['points'][$g['key']] ?? 0], $met);

            if ($all) {
                $awards[] = ['all_done', $rates['all_done']];
            }

            foreach ($awards as [$key, $points]) {
                $points = min($points, max(0, $rates['daily_cap'] - $earned));

                if ($this->points->award($agent, $points, "goal:{$day}:{$key}")) {
                    $earned += $points;
                }
            }

            // Brokerage campaigns: extra points it funds, up to its budget.
            foreach ($brokerage === null ? [] : $this->campaigns($brokerage, $day) as $campaign) {
                foreach ($met as $goal) {
                    if ($campaign->goal !== 'all' && $campaign->goal !== $goal['key']) {
                        continue;
                    }
                    $locked = PointsCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
                    $extra = min(($rates['points'][$goal['key']] ?? 0) * ($locked->multiplier - 1), max(0, $locked->budget - $locked->spent));

                    if ($brokerage !== null && $this->points->bonus($brokerage, $agent, $extra, "campaign:{$locked->id}:{$day}:{$goal['key']}")) {
                        $locked->forceFill(['spent' => $locked->spent + $extra])->save();
                        $bonus += $extra;
                    }
                }
            }

            $row->forceFill(['mode' => $mode, 'goals' => $goals, 'met' => count($met), 'total' => count($goals), 'points' => $earned, 'bonus' => $bonus, 'completed' => $all])->save();

            return $row;
        });
    }

    /**
     * The nightly run: yesterday settled for every agent, in their own time
     * zone, so points and streaks count even on days the app wasn't opened.
     */
    public function run(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $count = 0;

        User::query()->whereIn('id', AgentProfile::query()->select('user_id'))->orderBy('id')->chunkById(200, function ($agents) use ($now, &$count): void {
            foreach ($agents as $agent) {
                $this->settle($agent, $now->setTimezone($this->centre->settings($agent)->timezone)->subDay()->toDateString());
                $count++;
            }
        });

        return $count;
    }

    /**
     * The day's targets for a mode: the brokerage's, else REOS's.
     *
     * @return array<string, int>
     */
    public function targets(string $mode, ?Organisation $brokerage): array
    {
        $rates = $this->rates->current();
        $targets = $rates['targets'][$mode] ?? $rates['targets']['launch'];
        $own = $brokerage === null ? null : DailyGoalSetting::query()->where('organisation_id', $brokerage->id)->where('mode', $mode)->first();

        foreach (PointRates::GOALS as $goal) {
            $value = $own?->targets[$goal] ?? null;
            $targets[$goal] = is_numeric($value) ? max(0, (int) $value) : ($targets[$goal] ?? 0);
        }

        return $targets;
    }

    /**
     * The brokerage's targets for every mode, for the broker's page.
     *
     * @return array<string, array{targets: array<string, int>, own: bool}>
     */
    public function brokerageTargets(Organisation $brokerage): array
    {
        $own = DailyGoalSetting::query()->where('organisation_id', $brokerage->id)->pluck('mode')->all();
        $out = [];

        foreach (AgentModes::GOALS as $mode) {
            $out[$mode] = ['targets' => $this->targets($mode, $brokerage), 'own' => in_array($mode, $own, true)];
        }

        return $out;
    }

    /** @param array<string, int> $targets */
    public function saveTargets(User $broker, string $mode, array $targets): void
    {
        $brokerage = $this->directory->brokerageOf($broker, Role::Broker) ?? throw new NotFoundHttpException;

        if (! in_array($mode, AgentModes::GOALS, true)) {
            throw ValidationException::withMessages(['mode' => __('rewards.errors.mode')]);
        }

        $clean = [];

        foreach (PointRates::GOALS as $goal) {
            $clean[$goal] = max(0, min(50, (int) ($targets[$goal] ?? 0)));
        }

        $before = $this->targets($mode, $brokerage);
        DailyGoalSetting::query()->updateOrCreate(['organisation_id' => $brokerage->id, 'mode' => $mode], ['targets' => $clean, 'updated_by' => $broker->id]);
        $this->audit->record('daily_goals.updated', 'organisation', (string) $brokerage->id, ['mode' => $mode, 'before' => $before, 'after' => $clean], $broker, $brokerage->id);
    }

    /**
     * The current streak (days in a row with every goal met, ending today or
     * yesterday) and the best one.
     *
     * @return array{0: int, 1: int}
     */
    public function streaks(User $agent, string $today): array
    {
        /** @var list<string> $days */
        $days = array_values(array_filter(DailyGoalDay::query()->where('user_id', $agent->id)->where('completed', true)->orderByDesc('day')->limit(1000)->pluck('day')->all(), is_string(...)));
        $set = array_flip($days);
        $cursor = CarbonImmutable::parse($today);

        if (! isset($set[$today])) {
            $cursor = $cursor->subDay();
        }

        $streak = 0;

        while (isset($set[$cursor->toDateString()])) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        $best = 0;
        $run = 0;
        $previous = null;

        foreach (array_reverse($days) as $day) {
            $date = CarbonImmutable::parse($day);
            $run = $previous !== null && $previous->addDay()->isSameDay($date) ? $run + 1 : 1;
            $best = max($best, $run);
            $previous = $date;
        }

        return [$streak, max($best, $streak)];
    }

    /** @return list<PointsCampaign> */
    private function campaigns(Organisation $brokerage, string $day): array
    {
        return array_values(PointsCampaign::query()->where('organisation_id', $brokerage->id)
            ->whereDate('starts_on', '<=', $day)->whereDate('ends_on', '>=', $day)->whereColumn('spent', '<', 'budget')->orderBy('id')->get()->all());
    }

    private function goalPoints(string $key): int
    {
        return $this->rates->current()['points'][$key] ?? 0;
    }

    private static function done(string $key, User $agent, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return match ($key) {
            'conversations' => Activity::conversations($agent, $from, $to),
            'ce_lesson' => LessonProgress::query()->where('user_id', $agent->id)->whereBetween('completed_at', [$from->utc(), $to->utc()])->count(),
            'follow_ups' => Todo::query()->where('user_id', $agent->id)->whereBetween('done_at', [$from->utc(), $to->utc()])->count(),
            'post' => Activity::posts($agent, $from, $to),
            default => 0,
        };
    }
}
