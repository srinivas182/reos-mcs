<?php

declare(strict_types=1);

namespace App\Practice;

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
 * @phpstan-type Question array{id: int, n: int, topic: string, prompt: string, options: list<string>, choice: int|null, flagged: bool, feedback: array{correct: bool, right: int, explanation: string, source: string}|null}
 * @phpstan-type Attempt array{id: int, kind: string, mode: string, topic: string|null, total: int, answered: int, started_at: string, ends_at: string|null, seconds_left: int|null, submitted: bool, questions: list<Question>}
 * @phpstan-type TopicScore array{topic: string, correct: int, total: int, percent: int, weight: int}
 * @phpstan-type Results array{id: int, kind: string, score: int, correct: int, total: int, pass_line: int, previous: int|null, seconds: int, topics: list<TopicScore>, weakest: list<string>, strongest: list<string>, review: list<array{id: int, n: int, topic: string, prompt: string, options: list<string>, choice: int|null, right: int, correct: bool, explanation: string, source: string}>}
 */
final readonly class Tests
{
    /** Topic quizzes: 10 questions, untimed. */
    public const TOPIC_QUESTIONS = 10;

    /** The share of questions to pass (the Texas exams' 70%). */
    public const PASS = 70;

    public function __construct(private PersonStates $states, private StudyPlans $plans) {}

    /**
     * Start a test, or carry on with the same kind still open.
     *
     * @throws ValidationException when there aren't enough questions for it
     */
    public function start(User $user, string $kind, ?string $topic = null): TestAttempt
    {
        $open = TestAttempt::query()->where('user_id', $user->id)->where('kind', $kind)->where('topic', $topic)->whereNull('submitted_at')->latest('id')->first();

        if ($open !== null && ! $this->expire($open)) {
            return $open;
        }

        $state = $this->states->current($user);
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

        if ($n === false) {
            throw ValidationException::withMessages(['question' => __('practice.errors.notInTest')]);
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
        $byTopic = [];
        $correct = 0;

        foreach ($attempt->question_ids as $id) {
            $question = $questions->get($id);
            if (! $question instanceof QuizQuestion) {
                continue;
            }
            $topic = $question->topic->value;
            $byTopic[$topic] ??= ['correct' => 0, 'total' => 0];
            $byTopic[$topic]['total']++;
            if (($answers[$id] ?? null) === $question->correct_option) {
                $byTopic[$topic]['correct']++;
                $correct++;
            }
        }

        $attempt->forceFill([
            'submitted_at' => $at ?? CarbonImmutable::now(), 'correct' => $correct, 'by_topic' => $byTopic,
            'score' => (int) round(100 * $correct / max(1, $attempt->total)),
        ])->save();

        // Readiness and the plan follow from this result at once (screen 43).
        $this->plans->rebuild($attempt->user, $attempt->kind === TestAttempt::DIAGNOSTIC ? 'diagnostic' : 'test');

        return $attempt;
    }

    /** Ends a timed test that ran out of time (scored as it stood at the end); true when it has ended. */
    public function expire(TestAttempt $attempt): bool
    {
        if ($attempt->submitted_at === null && $attempt->ends_at !== null && $attempt->ends_at->isPast()) {
            $this->submit($attempt, CarbonImmutable::parse($attempt->ends_at));
        }

        return $attempt->submitted_at !== null;
    }

    /** @return Attempt */
    public function show(TestAttempt $attempt, string $language): array
    {
        $this->expire($attempt);
        $questions = $this->questions($attempt, $language);

        return [
            'id' => $attempt->id, 'kind' => $attempt->kind, 'mode' => $attempt->mode, 'topic' => $attempt->topic, 'total' => $attempt->total,
            'answered' => count(array_filter($questions, static fn (array $q): bool => $q['choice'] !== null)),
            'started_at' => $attempt->started_at->toIso8601String(), 'ends_at' => $attempt->ends_at?->toIso8601String(),
            'seconds_left' => $attempt->ends_at === null ? null : max(0, (int) now()->diffInSeconds($attempt->ends_at, false)),
            'submitted' => $attempt->submitted_at !== null, 'questions' => $questions,
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

        $previous = TestAttempt::query()->where('user_id', $attempt->user_id)->where('kind', $attempt->kind)->where('topic', $attempt->topic)
            ->whereNotNull('submitted_at')->where('id', '<', $attempt->id)->latest('id')->value('score');
        $review = [];

        foreach ($attempt->question_ids as $n => $id) {
            $question = $questions->get($id);
            if (! $question instanceof QuizQuestion) {
                continue;
            }
            $text = $question->in($language);
            $choice = $attempt->answers[$id] ?? null;
            $review[] = [
                'id' => $id, 'n' => $n + 1, 'topic' => $question->topic->value, 'prompt' => $text['question'], 'options' => $text['options'],
                'choice' => $choice, 'right' => $question->correct_option, 'correct' => $choice === $question->correct_option, 'explanation' => $text['explanation'], 'source' => $question->source,
            ];
        }

        $end = $attempt->submitted_at ?? now();

        return [
            'id' => $attempt->id, 'kind' => $attempt->kind, 'score' => $attempt->score ?? 0, 'correct' => $attempt->correct ?? 0, 'total' => $attempt->total, 'pass_line' => self::PASS,
            'previous' => is_numeric($previous) ? (int) $previous : null,
            'seconds' => (int) $attempt->started_at->diffInSeconds($end),
            'topics' => $topics,
            'weakest' => array_values(array_map(static fn (array $t): string => $t['topic'], array_filter($topics, static fn (array $t): bool => $t['percent'] < self::PASS))),
            'strongest' => array_values(array_map(static fn (array $t): string => $t['topic'], array_filter(array_reverse($topics), static fn (array $t): bool => $t['percent'] >= self::PASS))),
            'review' => $review,
        ];
    }

    /** @return list<Question> */
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
            $list[] = ['id' => $id, 'n' => $n + 1, 'topic' => $question->topic->value, 'prompt' => $text['question'], 'options' => $text['options'], 'choice' => $choice, 'flagged' => in_array($id, $flags, true), 'feedback' => $feedback];
        }

        return $list;
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
