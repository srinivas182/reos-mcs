<?php

declare(strict_types=1);

namespace App\Practice;

use App\Enums\QuizTopic;
use App\Models\QuizQuestion;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\States\PersonStates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Timed tests (A8.3, ADR 0032; candidate screens 40–43, web screens 7–8):
 * the Texas diagnostic (50 questions across the exam outline, 45 minutes,
 * exam mode: no feedback until the end) and topic quizzes (10 questions,
 * practice mode: why after each answer). Answers are saved after every tap;
 * a timed test ends on the server's clock whether or not the person is still
 * there, and is then scored as it stands.
 *
 * A9.1 (ADR 0035) adds the National and state portion tests and the full
 * simulation in the state's real format (ExamFormats): each portion is a
 * timed section, pretest items are asked but not scored, Exam mode gives
 * feedback only at the end and can't pause, Practice mode shows why after
 * each answer and can pause. A simulation's second portion starts when the
 * first ends, and only the current section's questions are sent.
 *
 * @phpstan-type Question array{id: int, n: int, topic: string, prompt: string, options: list<string>, choice: int|null, flagged: bool, feedback: array{correct: bool, right: int, explanation: string, source: string}|null}
 * @phpstan-type Section array{portion: string, from: int, count: int, scored: int, minutes: int, pass: int, started_at: string|null, ends_at: string|null, ended_at: string|null}
 * @phpstan-type Attempt array{id: int, kind: string, mode: string, topic: string|null, total: int, answered: int, started_at: string, ends_at: string|null, seconds_left: int|null, submitted: bool, paused: bool, section: array{index: int, of: int, portion: string, minutes: int, questions: int}|null, questions: list<Question>}
 * @phpstan-type TopicScore array{topic: string, correct: int, total: int, percent: int, weight: int}
 * @phpstan-type PortionScore array{portion: string, correct: int, total: int, pass: int, passed: bool, percent: int, previous: int|null, seconds: int, pace: int}
 * @phpstan-type Results array{id: int, kind: string, mode: string, score: int, correct: int, total: int, pass_line: int, passed: bool|null, previous: int|null, seconds: int, portions: list<PortionScore>, timing: array{seconds_per_question: int, pace: int}|null, topics: list<TopicScore>, weakest: list<string>, strongest: list<string>, review: list<array{id: int, n: int, topic: string, portion: string, prompt: string, options: list<string>, choice: int|null, right: int, correct: bool, scored: bool, explanation: string, source: string, lesson: array{id: int, seconds: int|null}|null}>}
 */
final readonly class Tests
{
    /** Topic quizzes: 10 questions, untimed. */
    public const TOPIC_QUESTIONS = 10;

    /** The share of questions to pass (the Texas exams' 70%). */
    public const PASS = 70;

    public function __construct(private PersonStates $states, private StudyPlans $plans, private ExamFormats $formats, private Readiness $readiness, private Drills $drills) {}

    /**
     * Start a test, or carry on with the same kind still open.
     *
     * @throws ValidationException when there aren't enough questions for it
     */
    public function start(User $user, string $kind, ?string $topic = null, ?string $mode = null): TestAttempt
    {
        $open = TestAttempt::query()->where('user_id', $user->id)->where('kind', $kind)->where('topic', $topic)->whereNull('submitted_at')->latest('id')->first();

        if ($open !== null && ! $this->expire($open)) {
            return $open;
        }

        $state = $this->states->current($user);

        if (in_array($kind, TestAttempt::PORTION_KINDS, true)) {
            return $this->startPortions($user, $state, $kind, $mode === 'practice' ? 'practice' : 'exam');
        }

        if (in_array($kind, [TestAttempt::DRILL, TestAttempt::RETEST], true)) {
            $ids = $kind === TestAttempt::DRILL ? $this->drills->drill($user, $state, $topic) : $this->drills->retest($user, $state, (string) $topic);

            return TestAttempt::query()->create([
                'user_id' => $user->id, 'kind' => $kind, 'mode' => $kind === TestAttempt::DRILL ? 'practice' : 'exam', 'state_code' => $state, 'topic' => $topic,
                'question_ids' => $ids, 'answers' => [], 'flags' => [], 'hints' => [], 'started_at' => CarbonImmutable::now(), 'ends_at' => null, 'total' => count($ids),
            ]);
        }

        $ids = $kind === TestAttempt::DIAGNOSTIC ? $this->diagnosticQuestions($state) : $this->topicQuestions($state, (string) $topic);
        $wanted = $kind === TestAttempt::DIAGNOSTIC ? DiagnosticBank::QUESTIONS : self::TOPIC_QUESTIONS;

        if (count($ids) < min($wanted, 5)) {
            throw ValidationException::withMessages(['kind' => __('practice.errors.noQuestions')]);
        }

        $now = CarbonImmutable::now();

        return TestAttempt::query()->create([
            'user_id' => $user->id, 'kind' => $kind, 'mode' => $kind === TestAttempt::DIAGNOSTIC ? 'exam' : 'practice', 'state_code' => $state, 'topic' => $topic,
            'question_ids' => $ids, 'answers' => [], 'flags' => [], 'started_at' => $now,
            'ends_at' => $kind === TestAttempt::DIAGNOSTIC ? $now->addMinutes(DiagnosticBank::MINUTES) : null, 'total' => count($ids),
        ]);
    }

    /**
     * A portion test or the full simulation: each portion its own timed
     * section, with the outline's share per topic, questions this person
     * hasn't seen first, and the pretest share left unscored.
     */
    private function startPortions(User $user, string $state, string $kind, string $mode): TestAttempt
    {
        $portions = match ($kind) {
            TestAttempt::NATIONAL => [ExamFormats::NATIONAL],
            TestAttempt::STATE => [ExamFormats::STATE],
            default => [ExamFormats::NATIONAL, ExamFormats::STATE],
        };
        $seen = $this->seen($user);
        $ids = [];
        $unscored = [];
        $sections = [];
        $now = CarbonImmutable::now();

        foreach ($portions as $i => $portion) {
            $format = $this->formats->portion($state, $portion);
            $picked = $this->portionQuestions($state, $portion, $format['questions'], $seen);

            if (count($picked) < 5) {
                throw ValidationException::withMessages(['kind' => __('practice.errors.noQuestions')]);
            }

            // A full set leaves the real pretest share unscored; a short one scores everything, with the pass mark scaled.
            $pretest = count($picked) === $format['questions'] ? $format['questions'] - $format['scored'] : 0;
            $unscored = [...$unscored, ...collect($picked)->shuffle()->take($pretest)->values()->all()];
            $scored = count($picked) - $pretest;
            $pass = $scored === $format['scored'] ? $format['pass'] : (int) ceil($format['pass'] * $scored / max(1, $format['scored']));
            $sections[] = [
                'portion' => $portion, 'from' => count($ids), 'count' => count($picked), 'scored' => $scored, 'minutes' => $format['minutes'], 'pass' => $pass,
                'started_at' => $i === 0 ? $now->toIso8601String() : null, 'ends_at' => $i === 0 ? $now->addMinutes($format['minutes'])->toIso8601String() : null, 'ended_at' => null,
            ];
            $ids = [...$ids, ...$picked];
        }

        return TestAttempt::query()->create([
            'user_id' => $user->id, 'kind' => $kind, 'mode' => $mode, 'state_code' => $state, 'topic' => null,
            'question_ids' => $ids, 'answers' => [], 'flags' => [], 'sections' => $sections, 'unscored' => $unscored,
            'started_at' => $now, 'ends_at' => CarbonImmutable::parse((string) $sections[0]['ends_at']), 'total' => count($ids),
        ]);
    }

    /**
     * Practice mode only: the clock stops until Resume.
     *
     * @throws ValidationException in exam mode, or once ended
     */
    public function pause(TestAttempt $attempt, bool $paused): TestAttempt
    {
        if ($attempt->mode !== 'practice' || $attempt->ends_at === null || $this->expire($attempt)) {
            throw ValidationException::withMessages(['attempt' => __('practice.errors.noPause')]);
        }

        return DB::transaction(function () use ($attempt, $paused): TestAttempt {
            $locked = TestAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($paused && $locked->paused_at === null) {
                $locked->forceFill(['paused_at' => CarbonImmutable::now()])->save();
            } elseif (! $paused && $locked->paused_at !== null && $locked->ends_at !== null) {
                // The time paused is added back to the current section.
                $away = (int) $locked->paused_at->diffInSeconds(CarbonImmutable::now());
                $ends = CarbonImmutable::parse($locked->ends_at)->addSeconds($away);
                $sections = $locked->sections;
                if ($sections !== null && ($i = self::current($sections)) !== null) {
                    $sections[$i]['ends_at'] = $ends->toIso8601String();
                }
                $locked->forceFill(['paused_at' => null, 'ends_at' => $ends, 'sections' => $sections])->save();
            }

            return $locked;
        });
    }

    /**
     * Done with this portion: a simulation moves on to the next portion,
     * which starts its own clock now; the last portion submits the test.
     */
    public function finish(TestAttempt $attempt): TestAttempt
    {
        if ($this->expire($attempt)) {
            return $attempt;
        }

        $sections = $attempt->sections;
        $i = $sections === null ? null : self::current($sections);

        if ($sections === null || $i === null || ! isset($sections[$i + 1])) {
            return $this->submit($attempt);
        }

        $this->advance($attempt, $i, CarbonImmutable::now());

        return $attempt;
    }

    /**
     * Save an answer (and whether it's flagged) the moment it's tapped.
     *
     * @return Question
     *
     * @throws ValidationException when the test has ended
     */
    public function answer(TestAttempt $attempt, int $question, ?int $choice, bool $flagged, string $language): array
    {
        if ($this->expire($attempt) || $attempt->submitted_at !== null) {
            throw ValidationException::withMessages(['attempt' => __('practice.errors.ended')]);
        }

        $n = array_search($question, $attempt->question_ids, true);

        if ($n === false || ! $this->inCurrentSection($attempt, $n)) {
            throw ValidationException::withMessages(['question' => __('practice.errors.notInTest')]);
        }

        if ($attempt->paused_at !== null) {
            throw ValidationException::withMessages(['attempt' => __('practice.errors.paused')]);
        }

        DB::transaction(function () use ($attempt, $question, $choice, $flagged): void {
            $locked = TestAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $answers = $locked->answers ?? [];
            // In practice mode an answer, once shown with why, stays.
            if ($choice !== null && ! ($locked->mode === 'practice' && isset($answers[$question]))) {
                $answers[$question] = $choice;
            }
            $flags = array_values(array_diff($locked->flags ?? [], [$question]));
            $locked->forceFill(['answers' => $answers, 'flags' => $flagged ? [...$flags, $question] : $flags])->save();
            $attempt->setRawAttributes($locked->getAttributes(), true);
        });

        return $this->questions($attempt, $language)[$n];
    }

    /** Score the test: once, by topic. */
    public function submit(TestAttempt $attempt, ?CarbonImmutable $at = null): TestAttempt
    {
        if ($attempt->submitted_at !== null) {
            return $attempt;
        }

        $questions = QuizQuestion::query()->whereIn('id', $attempt->question_ids)->get()->keyBy('id');
        $answers = $attempt->answers ?? [];
        $unscored = $attempt->unscored ?? [];
        $byTopic = [];
        $byPortion = [];
        $correct = 0;
        $scored = 0;
        $end = $at ?? CarbonImmutable::now();
        $sections = $attempt->sections;

        foreach ($attempt->question_ids as $n => $id) {
            $question = $questions->get($id);
            if (! $question instanceof QuizQuestion || in_array($id, $unscored, true)) {
                continue;
            }
            $right = ($answers[$id] ?? null) === $question->correct_option;
            $topic = $question->topic->value;
            $byTopic[$topic] ??= ['correct' => 0, 'total' => 0];
            $byTopic[$topic]['total']++;
            $byTopic[$topic]['correct'] += $right ? 1 : 0;
            $correct += $right ? 1 : 0;
            $scored++;

            foreach ($sections ?? [] as $section) {
                if ($n >= $section['from'] && $n < $section['from'] + $section['count']) {
                    $byPortion[$section['portion']] ??= ['correct' => 0, 'total' => 0, 'pass' => $section['pass'], 'passed' => false];
                    $byPortion[$section['portion']]['total']++;
                    $byPortion[$section['portion']]['correct'] += $right ? 1 : 0;
                }
            }
        }

        foreach ($byPortion as $portion => $score) {
            $byPortion[$portion]['passed'] = $score['correct'] >= $score['pass'];
        }

        if ($sections !== null) {
            foreach ($sections as $i => $section) {
                $sections[$i]['ended_at'] ??= $section['started_at'] === null ? null : $end->toIso8601String();
            }
        }

        $attempt->forceFill([
            'submitted_at' => $end, 'correct' => $correct, 'by_topic' => $byTopic, 'by_portion' => $sections === null ? null : $byPortion,
            'sections' => $sections, 'paused_at' => null,
            'score' => (int) round(100 * $correct / max(1, $sections === null ? $attempt->total : $scored)),
        ])->save();

        // Readiness and the plan follow from this result at once (screen 43).
        $this->plans->rebuild($attempt->user, $attempt->kind === TestAttempt::DIAGNOSTIC ? 'diagnostic' : 'test');
        $this->readiness->record($attempt->user);

        return $attempt;
    }

    /**
     * Ends what ran out of time: the current portion (moving a simulation
     * on), or the test (scored as it stood at the end). True when the test
     * has ended. A paused practice test doesn't run out.
     */
    public function expire(TestAttempt $attempt): bool
    {
        while ($attempt->submitted_at === null && $attempt->paused_at === null && $attempt->ends_at !== null && $attempt->ends_at->isPast()) {
            $sections = $attempt->sections;
            $i = $sections === null ? null : self::current($sections);

            if ($sections !== null && $i !== null && isset($sections[$i + 1])) {
                // The next portion starts now: its clock never ran while nobody was there.
                $this->advance($attempt, $i, CarbonImmutable::parse($attempt->ends_at), CarbonImmutable::now());

                continue;
            }

            $this->submit($attempt, CarbonImmutable::parse($attempt->ends_at));
        }

        return $attempt->submitted_at !== null;
    }

    /** Ends section $i at $ended and starts the next one at $start (default: $ended). */
    private function advance(TestAttempt $attempt, int $i, CarbonImmutable $ended, ?CarbonImmutable $start = null): void
    {
        $sections = $attempt->sections ?? [];
        $start ??= $ended;
        $ends = $start->addMinutes($sections[$i + 1]['minutes']);
        $sections[$i]['ended_at'] = $ended->toIso8601String();
        $sections[$i + 1]['started_at'] = $start->toIso8601String();
        $sections[$i + 1]['ends_at'] = $ends->toIso8601String();
        $attempt->forceFill(['sections' => $sections, 'ends_at' => $ends])->save();
    }

    /**
     * The section running now (started, not ended).
     *
     * @param  list<array{started_at: string|null, ended_at: string|null}>  $sections
     */
    private static function current(array $sections): ?int
    {
        foreach ($sections as $i => $section) {
            if ($section['started_at'] !== null && $section['ended_at'] === null) {
                return $i;
            }
        }

        return null;
    }

    private function inCurrentSection(TestAttempt $attempt, int $n): bool
    {
        $sections = $attempt->sections;

        if ($sections === null) {
            return true;
        }

        $i = self::current($sections);

        return $i !== null && $n >= $sections[$i]['from'] && $n < $sections[$i]['from'] + $sections[$i]['count'];
    }

    /** @return Attempt */
    public function show(TestAttempt $attempt, string $language): array
    {
        $this->expire($attempt);
        $all = $this->questions($attempt, $language);
        $sections = $attempt->sections;
        $i = $sections === null ? null : self::current($sections);
        // Only the portion being taken is sent; later portions stay on the server.
        $questions = $sections === null || $i === null ? ($sections === null ? $all : []) : array_slice($all, $sections[$i]['from'], $sections[$i]['count']);
        $left = $attempt->ends_at === null ? null : max(0, (int) ($attempt->paused_at ?? now())->diffInSeconds($attempt->ends_at, false));

        return [
            'id' => $attempt->id, 'kind' => $attempt->kind, 'mode' => $attempt->mode, 'topic' => $attempt->topic, 'total' => count($questions),
            'answered' => count(array_filter($questions, static fn (array $q): bool => $q['choice'] !== null)),
            'started_at' => $attempt->started_at->toIso8601String(), 'ends_at' => $attempt->ends_at?->toIso8601String(),
            'seconds_left' => $left, 'submitted' => $attempt->submitted_at !== null, 'paused' => $attempt->paused_at !== null,
            'section' => $sections === null || $i === null ? null : ['index' => $i + 1, 'of' => count($sections), 'portion' => $sections[$i]['portion'], 'minutes' => $sections[$i]['minutes'], 'questions' => $sections[$i]['count']],
            'questions' => $questions,
        ];
    }

    /**
     * The report (candidate screen 43, web screen 8): score and pass line,
     * change since the last of the same kind, topics weakest first with the
     * outline's weight, and every answer with why and its source.
     *
     * @return Results
     */
    public function results(TestAttempt $attempt, string $language): array
    {
        $questions = QuizQuestion::query()->whereIn('id', $attempt->question_ids)->get()->keyBy('id');
        $topics = [];

        foreach ($attempt->by_topic ?? [] as $topic => $score) {
            $topics[] = ['topic' => (string) $topic, 'correct' => $score['correct'], 'total' => $score['total'], 'percent' => (int) round(100 * $score['correct'] / max(1, $score['total'])), 'weight' => DiagnosticBank::WEIGHTS[$topic] ?? 0];
        }
        $order = array_flip(array_keys(DiagnosticBank::WEIGHTS));
        usort($topics, static fn (array $a, array $b): int => [$a['percent'], -$a['weight'], $order[$a['topic']] ?? 9] <=> [$b['percent'], -$b['weight'], $order[$b['topic']] ?? 9]);

        $earlier = TestAttempt::query()->where('user_id', $attempt->user_id)->where('kind', $attempt->kind)->where('topic', $attempt->topic)
            ->whereNotNull('submitted_at')->where('id', '<', $attempt->id)->latest('id')->first();
        $unscored = $attempt->unscored ?? [];
        $lessons = DB::table('review_items')->whereIn('id', $questions->pluck('review_item_id')->filter()->all())->pluck('lesson_id', 'id');
        $review = [];

        foreach ($attempt->question_ids as $n => $id) {
            $question = $questions->get($id);
            if (! $question instanceof QuizQuestion) {
                continue;
            }
            $text = $question->in($language);
            $choice = $attempt->answers[$id] ?? null;
            $lesson = $question->review_item_id === null ? null : ($lessons[$question->review_item_id] ?? null);
            $review[] = [
                'id' => $id, 'n' => $n + 1 - $this->sectionStart($attempt, $n), 'topic' => $question->topic->value, 'portion' => ExamFormats::portionOf($question->topic, $question->portion),
                'prompt' => $text['question'], 'options' => $text['options'], 'choice' => $choice, 'right' => $question->correct_option,
                'correct' => $choice === $question->correct_option, 'scored' => ! in_array($id, $unscored, true), 'explanation' => $text['explanation'], 'source' => $question->source,
                'lesson' => is_numeric($lesson) ? ['id' => (int) $lesson, 'seconds' => null] : null,
            ];
        }

        $end = $attempt->submitted_at ?? now();
        $portions = [];
        $answeredSeconds = 0;
        $answeredCount = 0;

        foreach ($attempt->sections ?? [] as $section) {
            $score = $attempt->by_portion[$section['portion']] ?? ['correct' => 0, 'total' => $section['scored'], 'pass' => $section['pass'], 'passed' => false];
            $seconds = $section['started_at'] === null ? 0 : (int) CarbonImmutable::parse($section['started_at'])->diffInSeconds(CarbonImmutable::parse($section['ended_at'] ?? $end->toIso8601String()));
            $before = $earlier?->by_portion[$section['portion']] ?? null;
            $portions[] = [
                'portion' => $section['portion'], 'correct' => $score['correct'], 'total' => $score['total'], 'pass' => $score['pass'], 'passed' => $score['passed'],
                'percent' => (int) round(100 * $score['correct'] / max(1, $score['total'])),
                'previous' => is_array($before) ? (int) round(100 * $before['correct'] / max(1, $before['total'])) : null,
                'seconds' => $seconds, 'pace' => (int) round($section['minutes'] * 60 / max(1, $section['count'])),
            ];
            $answeredSeconds += $seconds;
            $answeredCount += $section['count'];
        }

        $scoredTotal = $attempt->sections === null ? $attempt->total : array_sum(array_column($portions, 'total'));
        $passLine = $portions === [] ? self::PASS : (int) round(100 * array_sum(array_column($portions, 'pass')) / max(1, $scoredTotal));

        return [
            'id' => $attempt->id, 'kind' => $attempt->kind, 'mode' => $attempt->mode, 'score' => $attempt->score ?? 0, 'correct' => $attempt->correct ?? 0, 'total' => $scoredTotal, 'pass_line' => $passLine,
            'passed' => $portions === [] ? null : ! in_array(false, array_column($portions, 'passed'), true),
            'previous' => $earlier?->score,
            'seconds' => (int) $attempt->started_at->diffInSeconds($end),
            'portions' => $portions,
            'timing' => $portions === [] ? null : [
                'seconds_per_question' => (int) round($answeredSeconds / max(1, $answeredCount)),
                'pace' => (int) round(array_sum(array_map(static fn (array $p): int => $p['pace'], $portions)) / count($portions)),
            ],
            'topics' => $topics,
            'weakest' => array_values(array_map(static fn (array $t): string => $t['topic'], array_filter($topics, static fn (array $t): bool => $t['percent'] < self::PASS))),
            'strongest' => array_values(array_map(static fn (array $t): string => $t['topic'], array_filter(array_reverse($topics), static fn (array $t): bool => $t['percent'] >= self::PASS))),
            'review' => $review,
        ];
    }

    /**
     * One more question on the same topic, not in this test (answer review's Similar question).
     *
     * @return array{id: int, topic: string, prompt: string, options: list<string>, right: int, explanation: string, source: string}|null
     */
    public function similar(TestAttempt $attempt, int $questionId, string $language): ?array
    {
        $question = QuizQuestion::query()->find($questionId);

        if ($question === null || ! in_array($questionId, $attempt->question_ids, true)) {
            return null;
        }

        $other = QuizQuestion::query()->where('topic', $question->topic)->whereNull('retired_at')->whereNotIn('id', $attempt->question_ids)
            ->where(fn ($q) => $q->where('state_code', $attempt->state_code)->orWhereNull('state_code'))->inRandomOrder()->first();

        if ($other === null) {
            return null;
        }

        $text = $other->in($language);

        return ['id' => $other->id, 'topic' => $other->topic->value, 'prompt' => $text['question'], 'options' => $text['options'], 'right' => $other->correct_option, 'explanation' => $text['explanation'], 'source' => $other->source];
    }

    /**
     * Every question in order, numbered within its portion.
     *
     * @return list<Question>
     */
    private function questions(TestAttempt $attempt, string $language): array
    {
        $questions = QuizQuestion::query()->whereIn('id', $attempt->question_ids)->get()->keyBy('id');
        $answers = $attempt->answers ?? [];
        $flags = $attempt->flags ?? [];
        $list = [];

        foreach ($attempt->question_ids as $n => $id) {
            $question = $questions->get($id);
            if (! $question instanceof QuizQuestion) {
                continue;
            }
            $text = $question->in($language);
            $choice = $answers[$id] ?? null;
            // Practice mode shows why once answered; exam mode only in the report.
            $feedback = $attempt->mode === 'practice' && $choice !== null
                ? ['correct' => $choice === $question->correct_option, 'right' => $question->correct_option, 'explanation' => $text['explanation'], 'source' => $question->source]
                : null;
            $list[] = ['id' => $id, 'n' => $n + 1 - $this->sectionStart($attempt, $n), 'topic' => $question->topic->value, 'prompt' => $text['question'], 'options' => $text['options'], 'choice' => $choice, 'flagged' => in_array($id, $flags, true), 'feedback' => $feedback];
        }

        return $list;
    }

    private function sectionStart(TestAttempt $attempt, int $n): int
    {
        foreach ($attempt->sections ?? [] as $section) {
            if ($n >= $section['from'] && $n < $section['from'] + $section['count']) {
                return $section['from'];
            }
        }

        return 0;
    }

    /**
     * Questions this person has had in earlier tests, so a new attempt draws others first.
     *
     * @return list<int>
     */
    private function seen(User $user): array
    {
        $seen = [];

        foreach (TestAttempt::query()->where('user_id', $user->id)->pluck('question_ids') as $ids) {
            foreach (is_array($ids) ? $ids : [] as $id) {
                if (is_int($id)) {
                    $seen[$id] = true;
                }
            }
        }

        return array_keys($seen);
    }

    /**
     * A portion's questions: the outline's share per topic, unseen first,
     * then any of the portion's topics to make up the count. The starter
     * bank's sample questions count here (decision 2) until approved.
     *
     * @param  list<int>  $seen
     * @return list<int>
     */
    private function portionQuestions(string $state, string $portion, int $count, array $seen): array
    {
        $pool = QuizQuestion::query()->whereNull('retired_at')->where(fn ($q) => $q->where('state_code', $state)->orWhereNull('state_code'))
            ->get()->filter(static fn (QuizQuestion $q): bool => ExamFormats::portionOf($q->topic, $q->portion) === $portion)
            ->shuffle()->sortBy(static fn (QuizQuestion $q): int => in_array($q->id, $seen, true) ? 1 : 0);
        $weights = array_filter(DiagnosticBank::WEIGHTS, static fn (string $topic): bool => ExamFormats::portionOf(QuizTopic::from($topic), null) === $portion, ARRAY_FILTER_USE_KEY);
        $sum = max(1, array_sum($weights));
        $picked = [];

        foreach ($weights as $topic => $weight) {
            $share = (int) round($count * $weight / $sum);
            $picked = [...$picked, ...$pool->filter(static fn (QuizQuestion $q): bool => $q->topic->value === $topic)->take($share)->pluck('id')->all()];
        }

        $rest = $pool->pluck('id')->reject(static fn (mixed $id): bool => in_array($id, $picked, true))->take(max(0, $count - count($picked)))->all();

        return self::ints(collect([...$picked, ...$rest])->take($count)->shuffle());
    }

    /**
     * The diagnostic: REOS's Texas set, the outline's share per topic,
     * shuffled.
     *
     * @return list<int>
     */
    private function diagnosticQuestions(string $state): array
    {
        $pool = QuizQuestion::query()->where('in_diagnostic', true)->whereNull('retired_at')->where(fn ($q) => $q->where('state_code', $state)->orWhereNull('state_code'))->get();
        $ids = [];

        foreach (DiagnosticBank::WEIGHTS as $topic => $count) {
            $ids = [...$ids, ...$pool->filter(static fn (QuizQuestion $q): bool => $q->topic->value === $topic)->shuffle()->take($count)->pluck('id')->all()];
        }

        return self::ints(collect($ids)->shuffle());
    }

    /**
     * A topic quiz: approved questions on the topic (REOS's, and the
     * question bank's from screen 45), not samples, shuffled.
     *
     * @return list<int>
     */
    private function topicQuestions(string $state, string $topic): array
    {
        return self::ints(QuizQuestion::query()->where('topic', $topic)->whereNull('retired_at')->where('is_sample', false)
            ->where(fn ($q) => $q->where('state_code', $state)->orWhereNull('state_code'))
            ->inRandomOrder()->limit(self::TOPIC_QUESTIONS)->pluck('id'));
    }

    /**
     * @param  Collection<int, mixed>  $ids
     * @return list<int>
     */
    private static function ints(Collection $ids): array
    {
        return array_values(array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $ids->all()));
    }
}
