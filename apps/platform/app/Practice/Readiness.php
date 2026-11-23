<?php

declare(strict_types=1);

namespace App\Practice;

use App\Enums\QuizTopic;
use App\Models\ReadinessSnapshot;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\States\PersonStates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Readiness (A8.3, reworked in A9.2; ADR 0035): the predicted chance of
 * passing each exam portion on a new set of questions.
 *
 * Each topic's answers over the last 120 days count, older ones less (a
 * three-week half-life). A topic's ability is a Beta estimate of its share
 * right (one right and one wrong assumed, so a few answers mean little). A
 * portion's expected share is the outline-weighted mix of its topics, and
 * the pass chance is the chance of reaching the portion's pass mark over its
 * scored questions, allowing for both the luck of the draw and how unsure
 * the estimate still is. Readiness is the lower of the two portions: both
 * must pass. Booking unlocks at 90% on both and two recent simulations
 * passed (screen 56). Topics show a share and trend from 10 answers.
 *
 * Kept nightly and after every test (readiness_snapshots), and the study
 * plan follows it. Plus days to the exam and the streak.
 *
 * @phpstan-type Topic array{topic: string, portion: string, weight: int, percent: int|null, answers: int, shown: bool, trend: int|null, weak: bool}
 * @phpstan-type Map array{percent: int|null, portions: array{national: int|null, state: int|null}, line: int, booking: array{ready: bool, national: bool, state: bool, simulations: int, needed: int, url: string|null}, topics: list<Topic>, gain: array{topic: string, portion: string, points: int}|null, computed_on: string|null}
 */
final readonly class Readiness
{
    /** The booking line: both portions at or above it (screen 54). */
    public const LINE = 90;

    /** Passed simulations needed, most recent first, to book (web screen 8). */
    public const SIMULATIONS = 2;

    /** A topic shows its share and trend from this many answers (screen 44). */
    public const SHOWN_FROM = 10;

    /** Answers lose half their weight every this many days. */
    private const HALF_LIFE = 21;

    private const WINDOW_DAYS = 120;

    /** A fixed weak topic counts as this share right, for the gain shown on screen 44. */
    private const FIXED = 0.8;

    public function __construct(private ExamFormats $formats, private PersonStates $states) {}

    /**
     * Readiness for Today, the plan and results.
     *
     * @return array{percent: int|null, portions: array{national: int|null, state: int|null}, topics: array<string, int|null>, answers: array<string, int>, weakest: list<string>, tests: int, ready_to_book: bool}
     */
    public function of(User $user): array
    {
        $tally = $this->tally($user);
        $topics = [];

        foreach (DiagnosticBank::WEIGHTS as $topic => $weight) {
            $t = $tally['topics'][$topic];
            $topics[$topic] = $t['n'] <= 0.0 ? null : (int) round(100 * $t['c'] / $t['n']);
        }

        $portions = $this->portions($user, $tally['topics']);

        return [
            'percent' => self::overall($portions),
            'portions' => $portions,
            'topics' => $topics,
            'answers' => array_map(static fn (array $t): int => $t['answers'], $tally['topics']),
            'weakest' => self::weakest($topics),
            'tests' => $tally['tests'],
            'ready_to_book' => $this->booking($user, $portions)['ready'],
        ];
    }

    /**
     * The Readiness map (screen 54) and What you're good at (screen 44):
     * both portions against the booking line, what booking still needs,
     * every topic weakest first with its trend, and the gain from fixing
     * the weakest.
     *
     * @return Map
     */
    public function map(User $user): array
    {
        $tally = $this->tally($user);
        $portions = $this->portions($user, $tally['topics']);
        $now = $this->of($user);
        $before = ReadinessSnapshot::query()->where('user_id', $user->id)->where('day', '<=', StudyPlans::today()->subDays(7)->toDateString())->latest('day')->first();
        $order = array_flip(array_keys(DiagnosticBank::WEIGHTS));
        $topics = [];

        foreach (DiagnosticBank::WEIGHTS as $topic => $weight) {
            $percent = $now['topics'][$topic];
            $answers = $now['answers'][$topic];
            $shown = $answers >= self::SHOWN_FROM;
            $then = $before?->topics[$topic] ?? null;
            $topics[] = [
                'topic' => $topic, 'portion' => ExamFormats::portionOf(QuizTopic::from($topic), null), 'weight' => $weight,
                'percent' => $percent, 'answers' => $answers, 'shown' => $shown,
                'trend' => $shown && $percent !== null && is_int($then) ? $percent - $then : null,
                'weak' => in_array($topic, $now['weakest'], true),
            ];
        }

        // Weakest first; not enough answers yet after the rest, heavier topics first.
        usort($topics, static fn (array $a, array $b): int => [$a['percent'] === null ? 1 : 0, $a['percent'] ?? 0, -$a['weight'], $order[$a['topic']]]
            <=> [$b['percent'] === null ? 1 : 0, $b['percent'] ?? 0, -$b['weight'], $order[$b['topic']]]);

        return [
            'percent' => self::overall($portions),
            'portions' => $portions,
            'line' => self::LINE,
            'booking' => $this->booking($user, $portions),
            'topics' => $topics,
            'gain' => $this->gain($user, $tally['topics'], $portions, $now['weakest']),
            'computed_on' => ReadinessSnapshot::query()->where('user_id', $user->id)->latest('day')->first()?->day->toDateString(),
        ];
    }

    /** Keeps today's readiness (candidate's day), after a test and nightly. */
    public function record(User $user): ReadinessSnapshot
    {
        $now = $this->of($user);

        return ReadinessSnapshot::query()->updateOrCreate(['user_id' => $user->id, 'day' => StudyPlans::today()->toDateString()], [
            'national' => $now['portions']['national'], 'state' => $now['portions']['state'], 'percent' => $now['percent'],
            'topics' => $now['topics'], 'weakest' => $now['weakest'], 'ready_to_book' => $now['ready_to_book'],
        ]);
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
     * The chance of reaching `pass` of `scored` questions when the expected
     * share right is `mean` with uncertainty `variance`: a normal
     * approximation of the binomial, widened by the estimate's own spread,
     * with a continuity correction. Whole percent, 0 to 100.
     */
    public static function chance(float $mean, float $variance, int $scored, int $pass): int
    {
        $mu = $scored * $mean;
        $sigma = sqrt(max(1e-9, $scored * $mean * (1 - $mean) + $scored * $scored * $variance));

        return (int) round(100 * (1 - self::phi(($pass - 0.5 - $mu) / $sigma)));
    }

    /**
     * Decayed right and total answers per topic, and raw answer counts.
     *
     * @return array{topics: array<string, array{c: float, n: float, answers: int}>, tests: int}
     */
    private function tally(User $user): array
    {
        $now = CarbonImmutable::now();
        $attempts = TestAttempt::query()->where('user_id', $user->id)->whereNotNull('submitted_at')
            ->where('submitted_at', '>=', $now->subDays(self::WINDOW_DAYS))->latest('submitted_at')->limit(60)->get(['id', 'submitted_at', 'by_topic']);
        $topics = array_map(static fn (): array => ['c' => 0.0, 'n' => 0.0, 'answers' => 0], DiagnosticBank::WEIGHTS);

        foreach ($attempts as $attempt) {
            $age = max(0.0, (float) CarbonImmutable::parse($attempt->submitted_at)->diffInSeconds($now)) / 86400;
            $w = 0.5 ** ($age / self::HALF_LIFE);

            foreach ($attempt->by_topic ?? [] as $topic => $score) {
                if (isset($topics[$topic])) {
                    $topics[$topic]['c'] += $w * $score['correct'];
                    $topics[$topic]['n'] += $w * $score['total'];
                    $topics[$topic]['answers'] += $score['total'];
                }
            }
        }

        return ['topics' => $topics, 'tests' => $attempts->count()];
    }

    /**
     * Pass chance per portion; null for a portion with no answers yet.
     *
     * @param  array<string, array{c: float, n: float, answers: int}>  $topics
     * @return array{national: int|null, state: int|null}
     */
    private function portions(User $user, array $topics): array
    {
        $state = $this->states->current($user);
        $out = ['national' => null, 'state' => null];

        foreach ([ExamFormats::NATIONAL, ExamFormats::STATE] as $portion) {
            $in = array_filter(DiagnosticBank::WEIGHTS, static fn (string $t): bool => ExamFormats::portionOf(QuizTopic::from($t), null) === $portion, ARRAY_FILTER_USE_KEY);
            $sum = array_sum($in);
            if ($sum === 0) {
                continue;
            }
            $mean = 0.0;
            $variance = 0.0;
            $seen = 0.0;

            foreach ($in as $topic => $weight) {
                $t = $topics[$topic];
                $a = $t['c'] + 1;
                $b = $t['n'] - $t['c'] + 1;
                $p = $a / ($a + $b);
                $share = $weight / $sum;
                $mean += $share * $p;
                $variance += $share * $share * $p * (1 - $p) / ($a + $b + 1);
                $seen += $t['n'];
            }

            if ($seen > 0) {
                $format = $this->formats->portion($state, $portion);
                $out[$portion] = self::chance($mean, $variance, $format['scored'], $format['pass']);
            }
        }

        return $out;
    }

    /**
     * What booking still needs (screen 56): both portions at the line and
     * the last two simulations passed.
     *
     * @param  array{national: int|null, state: int|null}  $portions
     * @return array{ready: bool, national: bool, state: bool, simulations: int, needed: int, url: string|null}
     */
    private function booking(User $user, array $portions): array
    {
        $recent = TestAttempt::query()->where('user_id', $user->id)->where('kind', TestAttempt::SIMULATION)->whereNotNull('submitted_at')
            ->latest('submitted_at')->limit(self::SIMULATIONS)->get(['by_portion']);
        $passed = $recent->filter(static fn (TestAttempt $a): bool => $a->by_portion !== null && $a->by_portion !== [] && collect($a->by_portion)->every(static fn (array $p): bool => $p['passed']))->count();
        $national = ($portions['national'] ?? 0) >= self::LINE;
        $state = ($portions['state'] ?? 0) >= self::LINE;

        return [
            'ready' => $national && $state && $passed >= self::SIMULATIONS,
            'national' => $national, 'state' => $state, 'simulations' => $passed, 'needed' => self::SIMULATIONS,
            'url' => $this->formats->bookingUrl($this->states->current($user)),
        ];
    }

    /**
     * Pass-chance points gained by fixing the weakest topic (to 80% right) on its portion.
     *
     * @param  array<string, array{c: float, n: float, answers: int}>  $topics
     * @param  array{national: int|null, state: int|null}  $portions
     * @param  list<string>  $weakest
     * @return array{topic: string, portion: string, points: int}|null
     */
    private function gain(User $user, array $topics, array $portions, array $weakest): ?array
    {
        $topic = $weakest[0] ?? null;
        if ($topic === null) {
            return null;
        }

        $n = max($topics[$topic]['n'], (float) self::SHOWN_FROM);
        $fixed = [...$topics, $topic => ['c' => self::FIXED * $n, 'n' => $n, 'answers' => $topics[$topic]['answers']]];
        $portion = ExamFormats::portionOf(QuizTopic::from($topic), null);
        $after = $this->portions($user, $fixed)[$portion === ExamFormats::STATE ? 'state' : 'national'];
        $before = $portions[$portion === ExamFormats::STATE ? 'state' : 'national'];

        return $after === null || $before === null ? null : ['topic' => $topic, 'portion' => $portion, 'points' => max(0, $after - $before)];
    }

    /** @param  array{national: int|null, state: int|null}  $portions */
    private static function overall(array $portions): ?int
    {
        $known = array_filter($portions, static fn (?int $p): bool => $p !== null);

        return $known === [] ? null : min($known);
    }

    /**
     * Topics below the pass line, weakest first (heavier topics first on a
     * tie); topics not tested yet after them.
     *
     * @param  array<string, int|null>  $topics
     * @return list<string>
     */
    private static function weakest(array $topics): array
    {
        $known = array_filter($topics, static fn (?int $p): bool => $p !== null && $p < Tests::PASS);
        $order = array_flip(array_keys(DiagnosticBank::WEIGHTS));
        uksort($known, static fn (string $a, string $b): int => [$known[$a], -DiagnosticBank::WEIGHTS[$a], $order[$a]] <=> [$known[$b], -DiagnosticBank::WEIGHTS[$b], $order[$b]]);

        return array_map('strval', array_keys($known));
    }

    /** The standard normal distribution function (Abramowitz and Stegun 7.1.26, error under 1.5e-7). */
    private static function phi(float $z): float
    {
        $x = abs($z) / M_SQRT2;
        $t = 1 / (1 + 0.3275911 * $x);
        $erf = 1 - ((((1.061405429 * $t - 1.453152027) * $t + 1.421413741) * $t - 0.284496736) * $t + 0.254829592) * $t * exp(-$x * $x);

        return $z >= 0 ? 0.5 * (1 + $erf) : 0.5 * (1 - $erf);
    }
}
