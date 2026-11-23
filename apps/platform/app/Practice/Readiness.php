<?php

declare(strict_types=1);

namespace App\Practice;

use App\Models\TestAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Readiness from practice so far (A8.3, ADR 0032): each topic's share right
 * over the person's last five tests, weighted by the exam outline (the
 * diagnostic's shares). Unknown until the first test. Plus days to the exam
 * and the streak: days in a row with seat time or a test, today or
 * yesterday counting as "still going".
 */
final readonly class Readiness
{
    /** Tests that count towards readiness. */
    private const RECENT = 5;

    /**
     * @return array{percent: int|null, topics: array<string, int|null>, weakest: list<string>, tests: int}
     */
    public function of(User $user): array
    {
        $attempts = TestAttempt::query()->where('user_id', $user->id)->whereNotNull('submitted_at')->latest('submitted_at')->limit(self::RECENT)->get();
        $sum = [];

        foreach ($attempts as $attempt) {
            foreach ($attempt->by_topic ?? [] as $topic => $score) {
                $sum[$topic] ??= ['correct' => 0, 'total' => 0];
                $sum[$topic]['correct'] += $score['correct'];
                $sum[$topic]['total'] += $score['total'];
            }
        }

        $topics = [];
        $weighted = 0;
        $weights = 0;

        foreach (DiagnosticBank::WEIGHTS as $topic => $weight) {
            $score = $sum[$topic] ?? null;
            $percent = $score === null || $score['total'] === 0 ? null : (int) round(100 * $score['correct'] / $score['total']);
            $topics[$topic] = $percent;

            if ($percent !== null) {
                $weighted += $percent * $weight;
                $weights += $weight;
            }
        }

        return [
            'percent' => $weights === 0 ? null : (int) round($weighted / $weights),
            'topics' => $topics,
            'weakest' => $this->weakest($topics),
            'tests' => $attempts->count(),
        ];
    }

    /** Days until the exam: the middle of the exam month the person chose (null without one). */
    public static function examOn(User $user): ?CarbonImmutable
    {
        return $user->exam_month === null ? null : CarbonImmutable::parse($user->exam_month)->startOfMonth()->addDays(14);
    }

    public function daysToExam(User $user, ?CarbonImmutable $today = null): ?int
    {
        $exam = self::examOn($user);

        return $exam === null ? null : max(0, (int) ($today ?? CarbonImmutable::today())->diffInDays($exam, false));
    }

    /** Days in a row with study (seat time or a submitted test), ending today or yesterday. */
    public function streak(User $user, ?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $days = DB::table('seat_time_days')->where('user_id', $user->id)->where('seconds', '>', 0)->where('day', '>=', $today->subDays(366)->toDateString())->pluck('day')
            ->merge(TestAttempt::query()->where('user_id', $user->id)->whereNotNull('submitted_at')->where('submitted_at', '>=', $today->subDays(366))->pluck('submitted_at'))
            ->map(static fn (mixed $d): string => ($d instanceof \DateTimeInterface ? CarbonImmutable::instance($d)->setTimezone(StudyPlans::TIMEZONE) : CarbonImmutable::parse(is_string($d) ? $d : 'now'))->toDateString())
            ->unique()->flip();
        $day = $days->has($today->toDateString()) ? $today : $today->subDay();
        $streak = 0;

        while ($days->has($day->toDateString())) {
            $streak++;
            $day = $day->subDay();
        }

        return $streak;
    }

    /**
     * Topics below the pass line, weakest first (heavier topics first on a
     * tie); topics not tested yet after them.
     *
     * @param  array<string, int|null>  $topics
     * @return list<string>
     */
    private function weakest(array $topics): array
    {
        $known = array_filter($topics, static fn (?int $p): bool => $p !== null && $p < Tests::PASS);
        $order = array_flip(array_keys(DiagnosticBank::WEIGHTS));
        uksort($known, static fn (string $a, string $b): int => [$known[$a], -DiagnosticBank::WEIGHTS[$a], $order[$a]] <=> [$known[$b], -DiagnosticBank::WEIGHTS[$b], $order[$b]]);

        return array_map('strval', array_keys($known));
    }
}
