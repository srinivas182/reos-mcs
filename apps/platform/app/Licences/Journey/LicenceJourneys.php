<?php

declare(strict_types=1);

namespace App\Licences\Journey;

use App\Enums\QuizTopic;
use App\Models\BrokerOffer;
use App\Models\LicenceJourney;
use App\Models\State;
use App\Models\StateRule;
use App\Models\User;
use App\Notifications\ExamReminder;
use App\Practice\DiagnosticBank;
use App\Practice\Drills;
use App\Practice\ExamFormats;
use App\Practice\Readiness;
use App\Practice\StudyPlans;
use App\Services\StateRules\StateRules;
use App\Services\States\PersonStates;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * The licence journey (A10.3a, ADR 0041; candidate screens 16, 15, 14 and
 * 19). The steps come from the state's rules (screen 62: Texas from TREC),
 * each with what to do and a link; the candidate checks them off with
 * dates. Filing the application starts the state's window (Texas: a year).
 *
 * - **Ready to book** at the readiness line on both portions and two passed
 *   simulations; the vendor's site is a hand-off. The exam date the
 *   candidate enters drives the study plan and reminders a week and a day
 *   before (with the exam-day kit), then a nudge to enter the result.
 * - **Exam-day kit**: a checklist, the vendor's rules from the back office,
 *   directions and a two-minute calm-down.
 * - **Result**: passed or didn't, per portion. A passed portion is kept; a
 *   missed one gets the retake plan (its weak areas from the score report,
 *   then readiness) and the attempts left before more education.
 *
 * @phpstan-type Portion array{passed: bool|null, attempts: int, failed: int, on: string|null}
 * @phpstan-type JourneyData array{state: array{code: string, name: string}, steps: list<array{code: string, title: string, detail: string, url: string|null, done_on: string|null, auto: bool}>, window: array{filed_on: string|null, ends_on: string|null, days_left: int|null, months: int}, readiness: array{percent: int|null, national: int|null, state: int|null, line: int, ready: bool, simulations: int, needed: int}, exam: array{vendor: string|null, book_url: string|null, on: string|null, time: string|null, centre: string|null, days_to: int|null, directions_url: string|null}, kit: array{items: list<array{key: string, done: bool}>, rules: string|null}, results: array{national: Portion, state: Portion, can_enter: bool, attempts_allowed: int, passed_both: bool}, retake: array{portions: list<string>, topics: list<string>, more_education: bool, attempts_left: array<string, int>}|null, news: 'prompt'|'shared'|'skipped'|null, offers: array{opted_in: bool, city: string|null, open: int}}
 */
final readonly class LicenceJourneys
{
    public const KIT = ['id', 'confirmation', 'final_simulation', 'flashcards', 'sleep'];

    public const PORTIONS = [ExamFormats::NATIONAL, ExamFormats::STATE];

    /** Topics in a retake plan. */
    private const RETAKE_TOPICS = 3;

    public function __construct(
        private StateRules $rules,
        private PersonStates $states,
        private Readiness $readiness,
        private Drills $drills,
        private StudyPlans $plans,
    ) {}

    public function journey(User $user): LicenceJourney
    {
        // Candidates studying for a licence (the learning.study permission).
        abort_unless($user->can('learning.study'), 403);

        return LicenceJourney::query()->firstOrCreate(['user_id' => $user->id], [
            'state_code' => $this->states->current($user), 'steps' => [], 'kit' => [], 'attempts' => [], 'weak_topics' => [], 'reminded' => [],
        ]);
    }

    /** @return JourneyData */
    public function show(User $user): array
    {
        $journey = $this->journey($user);
        $state = $journey->state_code;
        $rules = $this->rules->inForce($state, CarbonImmutable::now());
        $today = StudyPlans::today();
        $results = $this->results($journey);
        $passedBoth = $results['national']['passed'] === true && $results['state']['passed'] === true;
        $months = self::number($rules['licensing.application_months']->value ?? null) ?? 12;
        $ends = $journey->application_filed_on === null ? null : self::day($journey->application_filed_on)->addMonths($months);
        $map = $this->readiness->map($user);
        $vendor = $rules['exam.vendor']->value['text'] ?? null;
        $dayRules = $rules['exam.day_rules']->value['text'] ?? null;

        $steps = [];

        foreach ($this->stepsOf($rules) as $step) {
            // The exam step checks itself off once both portions are passed.
            $auto = $step['code'] === 'exam' && $passedBoth;
            $steps[] = $step + [
                'done_on' => $auto ? max((string) $results['national']['on'], (string) $results['state']['on']) : ($journey->steps[$step['code']] ?? null),
                'auto' => $auto,
            ];
        }

        return [
            'state' => ['code' => $state, 'name' => State::query()->where('code', $state)->first()->name ?? $state],
            'steps' => $steps,
            'window' => [
                'filed_on' => $journey->application_filed_on?->toDateString(),
                'ends_on' => $ends?->toDateString(),
                'days_left' => $ends === null ? null : (int) $today->diffInDays($ends, false),
                'months' => $months,
            ],
            'readiness' => [
                'percent' => $map['percent'], 'national' => $map['portions']['national'], 'state' => $map['portions']['state'], 'line' => $map['line'],
                'ready' => $map['booking']['ready'], 'simulations' => $map['booking']['simulations'], 'needed' => $map['booking']['needed'],
            ],
            'exam' => [
                'vendor' => is_string($vendor) ? $vendor : null,
                'book_url' => $map['booking']['url'],
                'on' => $journey->exam_on?->toDateString(),
                'time' => $journey->exam_time,
                'centre' => $journey->test_centre,
                'days_to' => $journey->exam_on === null ? null : (int) $today->diffInDays(self::day($journey->exam_on), false),
                'directions_url' => $journey->test_centre === null ? null : 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($journey->test_centre),
            ],
            'kit' => [
                'items' => array_map(static fn (string $key): array => ['key' => $key, 'done' => in_array($key, $journey->kit, true)], self::KIT),
                'rules' => is_string($dayRules) ? $dayRules : null,
            ],
            'results' => $results + [
                // After the exam date, until both portions are passed.
                'can_enter' => ! $passedBoth && $journey->exam_on !== null && self::day($journey->exam_on)->lessThanOrEqualTo($today),
                'attempts_allowed' => $this->attemptsAllowed($rules),
                'passed_both' => $passedBoth,
            ],
            'retake' => $this->retake($user, $journey, $results, $this->attemptsAllowed($rules)),
            // After passing: share the news (f05), until shared or "Not now" (A10.3b).
            'news' => $passedBoth ? ($journey->news === 'shared' ? 'shared' : ($journey->news === 'skipped' ? 'skipped' : 'prompt')) : null,
            // Broker offers (screen 17): opted in, and how many are waiting.
            'offers' => [
                'opted_in' => $journey->offers_opt_in,
                'city' => $journey->offers_city,
                'open' => BrokerOffer::query()->where('candidate_id', $user->id)->where('status', BrokerOffer::OPEN)->count(),
            ],
        ];
    }

    /** The candidate checks a step off (with its date), or un-checks it. Filing the application starts the window. */
    public function check(User $user, string $code, bool $done, ?CarbonImmutable $on): void
    {
        $journey = $this->journey($user);
        $codes = array_column($this->stepsOf($this->rules->inForce($journey->state_code, CarbonImmutable::now())), 'code');

        if (! in_array($code, $codes, true)) {
            throw ValidationException::withMessages(['step' => __('licence.errors.step')]);
        }

        $on = $on === null ? StudyPlans::today() : self::day($on);

        if ($on->greaterThan(StudyPlans::today())) {
            throw ValidationException::withMessages(['on' => __('licence.errors.future')]);
        }

        $steps = $journey->steps;

        if ($done) {
            $steps[$code] = $on->toDateString();
        } else {
            unset($steps[$code]);
        }

        $journey->forceFill([
            'steps' => $steps,
            'application_filed_on' => $code === 'application' ? ($done ? $on->toDateString() : null) : $journey->application_filed_on,
        ])->save();
    }

    /** The exam date (and time and test centre) from the vendor's confirmation; the plan and reminders follow it. */
    public function book(User $user, ?CarbonImmutable $on, ?string $time, ?string $centre): void
    {
        $journey = $this->journey($user);
        $on = $on === null ? null : self::day($on);

        if ($on !== null && $on->lessThan(StudyPlans::today())) {
            throw ValidationException::withMessages(['exam_on' => __('licence.errors.past')]);
        }

        if ($on !== null && $journey->application_filed_on !== null) {
            $months = self::number($this->rules->inForce($journey->state_code, CarbonImmutable::now())['licensing.application_months']->value ?? null) ?? 12;

            if ($on->greaterThanOrEqualTo(self::day($journey->application_filed_on)->addMonths($months))) {
                throw ValidationException::withMessages(['exam_on' => __('licence.errors.afterWindow')]);
            }
        }

        $journey->forceFill(['exam_on' => $on?->toDateString(), 'exam_time' => $on === null ? null : $time, 'test_centre' => $on === null ? null : $centre, 'kit' => []])->save();
        $this->plans->rebuild($user, 'exam_booked');
    }

    /** Ticks an exam-day checklist item. */
    public function tick(User $user, string $item, bool $done): void
    {
        if (! in_array($item, self::KIT, true)) {
            throw ValidationException::withMessages(['item' => __('licence.errors.item')]);
        }

        $journey = $this->journey($user);
        $kit = array_values(array_diff($journey->kit, [$item]));
        $journey->forceFill(['kit' => $done ? [...$kit, $item] : $kit])->save();
    }

    /**
     * The result, per portion (null: not taken this time). A passed portion
     * is kept; a missed one needs a new exam date and gets the retake plan.
     *
     * @param  array{national: bool|null, state: bool|null}  $passed
     * @param  list<string>  $weakTopics  from the score report, for the retake plan only
     */
    public function enter(User $user, array $passed, array $weakTopics): void
    {
        $journey = $this->journey($user);
        $results = $this->results($journey);
        $today = StudyPlans::today();

        if ($journey->exam_on === null || self::day($journey->exam_on)->greaterThan($today)) {
            throw ValidationException::withMessages(['result' => __('licence.errors.notYet')]);
        }

        $attempts = $journey->attempts;
        $entered = false;
        $day = $journey->exam_on->toDateString();

        foreach (self::PORTIONS as $portion) {
            if ($passed[$portion] === null || $results[$portion]['passed'] === true) {
                continue;
            }

            $attempts[] = ['portion' => $portion, 'passed' => $passed[$portion], 'on' => $day];
            $entered = true;
        }

        if (! $entered) {
            throw ValidationException::withMessages(['result' => __('licence.errors.nothing')]);
        }

        $failed = in_array(false, array_values($passed), true);
        $after = $this->results($journey->forceFill(['attempts' => $attempts]));
        $journey->forceFill([
            // Both portions passed: the day it happened (A10.3b's passes).
            'passed_on' => $after['national']['passed'] === true && $after['state']['passed'] === true ? $day : null,
            'attempts' => $attempts,
            'weak_topics' => $failed ? array_values(array_intersect(array_keys(DiagnosticBank::WEIGHTS), $weakTopics)) : [],
            // A missed portion is retaken on a new date.
            'exam_on' => $failed ? null : $journey->exam_on,
            'exam_time' => $failed ? null : $journey->exam_time,
            'test_centre' => $failed ? null : $journey->test_centre,
        ])->save();

        if ($failed) {
            $this->plans->rebuild($user, 'retake');
        }
    }

    /**
     * Daily: a week before and the day before the exam, and the day after
     * (until a result is entered). Each once.
     */
    public function remindDue(): int
    {
        $today = StudyPlans::today();
        $sent = 0;

        foreach (LicenceJourney::query()->whereNotNull('exam_on')->where('exam_on', '>=', $today->subDays(14)->toDateString())->with('user')->get() as $journey) {
            // Compared as Texas calendar days.
            if ($journey->exam_on === null) {
                continue;
            }

            $exam = self::day($journey->exam_on);

            $taken = collect($journey->attempts)->contains(static fn (array $a): bool => $a['on'] === $exam->toDateString());
            $when = match (true) {
                $today->equalTo($exam->subDays(7)) => ExamReminder::WEEK,
                $today->equalTo($exam->subDay()) => ExamReminder::DAY,
                $today->greaterThan($exam) && ! $taken => ExamReminder::RESULT,
                default => null,
            };
            $key = $when === null ? null : $when.':'.$exam->toDateString();

            if ($key !== null && ! in_array($key, $journey->reminded, true)) {
                $journey->forceFill(['reminded' => [...$journey->reminded, $key]])->save();
                $journey->user->notify(new ExamReminder($journey, (string) $when));
                $sent++;
            }
        }

        return $sent;
    }

    /** @return array{national: Portion, state: Portion} */
    private function results(LicenceJourney $journey): array
    {
        $out = [];

        foreach (self::PORTIONS as $portion) {
            $mine = array_values(array_filter($journey->attempts, static fn (array $a): bool => $a['portion'] === $portion));
            $last = $mine === [] ? null : $mine[count($mine) - 1];
            $passed = collect($mine)->firstWhere('passed', true);
            $out[$portion] = [
                'passed' => $passed !== null ? true : ($last === null ? null : false),
                'attempts' => count($mine),
                'failed' => count(array_filter($mine, static fn (array $a): bool => ! $a['passed'])),
                'on' => $passed['on'] ?? $last['on'] ?? null,
            ];
        }

        return ['national' => $out['national'], 'state' => $out['state']];
    }

    /**
     * @param  array{national: Portion, state: Portion}  $results
     * @return array{portions: list<string>, topics: list<string>, more_education: bool, attempts_left: array<string, int>}|null
     */
    private function retake(User $user, LicenceJourney $journey, array $results, int $allowed): ?array
    {
        $missed = array_values(array_filter(self::PORTIONS, static fn (string $p): bool => $results[$p]['passed'] === false));

        if ($missed === []) {
            return null;
        }

        $inMissed = static fn (string $topic): bool => in_array(ExamFormats::portionOf(QuizTopic::from($topic), null), $missed, true);
        $topics = array_values(array_unique([
            ...array_filter($journey->weak_topics, $inMissed),
            ...array_filter($this->drills->weakestTopics($user, count(DiagnosticBank::WEIGHTS)), $inMissed),
        ]));
        $left = [];

        foreach ($missed as $portion) {
            $left[$portion] = max(0, $allowed - $results[$portion]['failed']);
        }

        return [
            'portions' => $missed,
            'topics' => array_slice($topics, 0, self::RETAKE_TOPICS),
            'more_education' => in_array(0, $left, true),
            'attempts_left' => $left,
        ];
    }

    /**
     * @param  array<string, StateRule>  $rules
     * @return list<array{code: string, title: string, detail: string, url: string|null}>
     */
    private function stepsOf(array $rules): array
    {
        $steps = [];

        $list = $rules['licensing.steps']->value['steps'] ?? [];

        foreach (is_array($list) ? $list : [] as $step) {
            if (is_array($step) && is_string($step['code'] ?? null) && is_string($step['title'] ?? null)) {
                $steps[] = [
                    'code' => $step['code'], 'title' => $step['title'],
                    'detail' => is_string($step['detail'] ?? null) ? $step['detail'] : '',
                    'url' => is_string($step['url'] ?? null) ? $step['url'] : null,
                ];
            }
        }

        return $steps;
    }

    /** @param  array<string, StateRule>  $rules */
    private function attemptsAllowed(array $rules): int
    {
        return self::number($rules['exam.attempts']->value ?? null) ?? 3;
    }

    /** A calendar day in Texas time, as the study plan counts days. */
    private static function day(CarbonImmutable $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date->toDateString(), StudyPlans::TIMEZONE);
    }

    private static function number(mixed $value): ?int
    {
        return is_array($value) && is_numeric($value['number'] ?? null) ? (int) $value['number'] : null;
    }
}
